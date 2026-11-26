<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\Html\HtmlAffordance;
use BEAR\Dev\Http\Exception\HalLinkNotFoundException;
use BEAR\Dev\LocationFollower;
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
use function sprintf;
use function strtoupper;

/**
 * Follows the links of a `#link=` fragment, which HTTP does not carry to the server
 *
 * The links are followed as DeclaredMethodLinker follows them in process, from the rels the representation carries.
 *
 * @internal
 */
final class LinkFollower
{
    private readonly LocationFollower $locationFollower;

    public function __construct(
        private readonly HrefExtractor $hrefExtractor,
        private readonly QueryMerger $queryMerger,
    ) {
        $this->locationFollower = new LocationFollower();
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
     * the href by top-level name, as DeclaredMethodLinker overlays it on the expanded href: `user[name]` replaces all of `user`.
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
     * The resource that answers a response: itself, or the one its Location names
     *
     * @param Closure(string, string, array<string, mixed>): ResourceObject $send
     */
    private function located(ResourceObject $response, Closure $send): ResourceObject
    {
        return ($this->locationFollower)($response, static fn (string $path): ResourceObject => $send('GET', $path, []));
    }
}
