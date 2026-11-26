<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\Html\HtmlAffordance;
use BEAR\Dev\Http\Exception\HalLinkNotFoundException;
use BEAR\Dev\QueryMerger;
use BEAR\Resource\Code;
use BEAR\Resource\Exception\LinkException;
use BEAR\Resource\Exception\LinkRelException;
use BEAR\Resource\LinkType;
use BEAR\Resource\ResourceObject;
use Closure;
use LogicException;

use function array_merge;
use function explode;
use function is_array;
use function preg_match;
use function sprintf;
use function strtolower;
use function strtoupper;

/**
 * Follows the links of a `#link=` fragment, which HTTP does not carry to the server
 *
 * The links are followed as bear/resource follows them in process, from the rels the representation carries.
 *
 * @internal
 */
final class LinkFollower
{
    /** A Location a link follows: a path on this server */
    private const LOCATION_PATH = '#\A/(?!/)#';

    public function __construct(
        private readonly HrefExtractor $hrefExtractor,
        private readonly QueryMerger $queryMerger,
    ) {
    }

    /**
     * @param array<string, mixed>                                          $query
     * @param Closure(string, string, array<string, mixed>): ResourceObject $send  sends a method, a path and a query
     */
    public function __invoke(string $method, string $uri, array $query, Closure $send): ResourceObject
    {
        $links = LinkType::listFromUri($uri);
        foreach ($links as $link) {
            if ($link->type === LinkType::CRAWL_LINK) {
                throw new LogicException(sprintf('A crawl link (%s) is not followed over HTTP', $link->key));
            }
        }

        $response = $send($method, $uri, $query);
        if ($links === []) {
            return $response;
        }

        $current = $this->located($response, $send);
        foreach ($links as $link) {
            if ($current->code >= Code::BAD_REQUEST) {
                return $current;
            }

            $linked = $this->located($this->follow($link, $current, $send), $send);
            if ($link->type === LinkType::NEW_LINK) {
                $body = is_array($current->body) ? $current->body : [];
                $body[$link->key] = $linked->body;
                $current->body = $body;

                continue;
            }

            $current = $linked;
        }

        return $current;
    }

    /**
     * The href of a rel of a response, without the fragment, which names a part of the page HTTP does not request
     *
     * @throws HalLinkNotFoundException
     */
    public function href(string $rel, ResourceObject|null $ro): string
    {
        if (! $ro instanceof ResourceObject) {
            throw new HalLinkNotFoundException('A source ResourceObject is required to follow a link.');
        }

        $href = $this->hrefExtractor->href($rel, $ro);
        if ($href === null) {
            throw new HalLinkNotFoundException(sprintf('Link rel `%s` is not available.', $rel));
        }

        [$requestUri] = explode('#', $href, 2);

        return $requestUri;
    }

    /**
     * Requests the rel's href with the method the representation gives it
     *
     * A form's hidden inputs go with it, as a browser sends them. The link query is overlaid on them and on the query of
     * the href by top-level name, as bear/resource overlays it on the expanded href: `user[name]` replaces all of `user`.
     *
     * @param Closure(string, string, array<string, mixed>): ResourceObject $send
     */
    private function follow(LinkType $link, ResourceObject $current, Closure $send): ResourceObject
    {
        $found = $this->hrefExtractor->link($link->key, $current);
        if ($found === null) {
            throw new LinkRelException(sprintf('rel:%s uri:%s', $link->key, (string) $current->uri), 500);
        }

        $method = strtoupper($found->method);
        $href = ($this->queryMerger)($found->href, []);
        $query = array_merge($this->hrefExtractor->hidden($link->key, $current), $href->query, $link->query);
        if ($method === 'POST' && isset($query[HtmlAffordance::METHOD_OVERRIDE])) {
            throw new LinkException(sprintf('The %s link of %s posts %s, which would turn the POST into another method', $link->key, (string) $current->uri, HtmlAffordance::METHOD_OVERRIDE), 500);
        }

        return $send($method, $href->path, $query);
    }

    /**
     * The resource that answers a response
     *
     * A 303, or a 201 with a Location, is answered by the resource its Location names, requested once with GET.
     *
     * @param Closure(string, string, array<string, mixed>): ResourceObject $send
     */
    private function located(ResourceObject $response, Closure $send): ResourceObject
    {
        if (! $this->redirects($response)) {
            return $response;
        }

        $location = $this->location($response);
        if ($location === null || preg_match(self::LOCATION_PATH, $location) !== 1) {
            throw new LinkException(sprintf('%s answered %d with the Location %s; a link follows only a path', (string) $response->uri, $response->code, $location ?? '(none)'), 500);
        }

        $located = $send('GET', $location, []);
        if ($this->redirects($located)) {
            throw new LinkException(sprintf('%s, the Location of %s, redirects again; a link follows one Location', (string) $located->uri, (string) $response->uri), 500);
        }

        return $located;
    }

    /** A 303, or a 201 that names the created resource in a Location */
    private function redirects(ResourceObject $ro): bool
    {
        return $ro->code === Code::SEE_OTHER || ($ro->code === Code::CREATED && $this->location($ro) !== null);
    }

    /** The Location header of a response, whatever the case of its name */
    private function location(ResourceObject $ro): string|null
    {
        foreach ($ro->headers as $name => $value) {
            if (strtolower($name) === 'location') {
                return $value;
            }
        }

        return null;
    }
}
