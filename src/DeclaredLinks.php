<?php

declare(strict_types=1);

namespace BEAR\Dev;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\Exception\LinkException;
use BEAR\Resource\Exception\LinkQueryException;
use BEAR\Resource\Exception\LinkRelException;
use BEAR\Resource\FactoryInterface;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\LinkCrawlerInterface;
use BEAR\Resource\LinkType;
use BEAR\Resource\Method;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;
use ReflectionMethod;

use function array_map;
use function array_merge;
use function implode;
use function is_array;
use function sprintf;
use function strtolower;
use function ucfirst;
use function uri_template;

/**
 * The links a resource method declares with #[Link], and the requests they make
 *
 * @internal
 */
final class DeclaredLinks
{
    private readonly LocationFollower $location;

    public function __construct(
        private readonly InvokerInterface $invoker,
        private readonly FactoryInterface $factory,
    ) {
        $this->location = new LocationFollower();
    }

    /**
     * The resource that answers a response, and the method it was requested with
     *
     * @return array{ResourceObject, Method}
     *
     * @throws LinkException
     */
    public function located(ResourceObject $response, Method $method): array
    {
        $located = ($this->location)($response, function (string $path) use ($response): ResourceObject {
            $ro = $this->factory->newInstance("{$response->uri->scheme}://{$response->uri->host}{$path}");

            return $this->invoker->invoke(new Request($this->invoker, $ro, Method::GET, $ro->uri->query));
        });

        return $located === $response ? [$response, $method] : [$located, Method::GET];
    }

    /**
     * The request of a self or new link, made as ResourceInterface::href() makes it
     *
     * The method the link declares requests its href expanded with the body overlaid by the link query; the link query
     * is overlaid on the expanded query.
     *
     * @throws LinkException
     * @throws LinkQueryException
     * @throws LinkRelException
     */
    public function request(LinkType $link, ResourceObject $current, Method $from): Request
    {
        $annotations = $this->annotations($current, $from);
        foreach ($annotations as $annotation) {
            if ($annotation->rel !== $link->key) {
                continue;
            }

            $body = (array) $current->body;
            $ro = $this->factory->newInstance(uri_template($annotation->href, array_merge($body, $link->query)));
            $method = Method::tryFrom(strtolower($annotation->method));
            if ($method === null) {
                throw new LinkException(sprintf("rel:%s class:%s declares the method '%s', which a resource does not take", $link->key, $current::class, $annotation->method), 500);
            }

            $ro->uri->method = $method->value;
            $ro->uri->query = array_merge($ro->uri->query, $link->query);

            return new Request($this->invoker, $ro, $method, $ro->uri->query);
        }

        $declared = implode(',', array_map(static fn (Link $annotation): string => $annotation->rel, $annotations));

        throw new LinkRelException(sprintf('rel:%s class:%s method:on%s declared:%s', $link->key, $current::class, ucfirst($from->value), $declared), 500);
    }

    /** Follows a crawl link with GET, as the resource client does */
    public function crawl(LinkType $link, ResourceObject $current, Method $from, LinkCrawlerInterface $linkCrawler): ResourceObject
    {
        $annotations = $this->annotations($current, $from);
        $isList = $linkCrawler->isList($current->body);
        /** @var list<array<string, mixed>> $bodyList */
        $bodyList = $isList ? (array) $current->body : [$current->body];
        $linkCrawler->crawl($annotations, $link, $bodyList);
        $current->body = $isList ? $bodyList : $bodyList[0];

        return $current;
    }

    /**
     * @return list<Link>
     *
     * @throws LinkQueryException
     */
    private function annotations(ResourceObject $ro, Method $method): array
    {
        if (! is_array($ro->body)) {
            throw new LinkQueryException('Only array is allowed for link in ' . $ro::class, 500);
        }

        $attributes = (new ReflectionMethod($ro, 'on' . ucfirst($method->value)))->getAttributes(Link::class);

        return array_map(static fn ($attribute): Link => $attribute->newInstance(), $attributes);
    }
}
