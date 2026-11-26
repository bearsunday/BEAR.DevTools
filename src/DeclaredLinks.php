<?php

declare(strict_types=1);

namespace BEAR\Dev;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\Exception\LinkQueryException;
use BEAR\Resource\Exception\LinkRelException;
use BEAR\Resource\FactoryInterface;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\LinkCrawlerInterface;
use BEAR\Resource\LinkType;
use BEAR\Resource\Method;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;
use Ray\Di\Di\Set;
use Ray\Di\ProviderInterface;
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
    /** @param ProviderInterface<LinkCrawlerInterface> $linkCrawlerProvider */
    public function __construct(
        private readonly InvokerInterface $invoker,
        private readonly FactoryInterface $factory,
        #[Set(LinkCrawlerInterface::class)]
        private readonly ProviderInterface $linkCrawlerProvider,
    ) {
    }

    /**
     * The request of a self or new link, made as ResourceInterface::href() makes it
     *
     * The method the link declares requests its href expanded with the body overlaid by the link query; the link query
     * is overlaid on the expanded query.
     *
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
            $method = Method::from(strtolower($annotation->method));
            $ro->uri->method = $method->value;
            $ro->uri->query = array_merge($ro->uri->query, $link->query);

            return new Request($this->invoker, $ro, $method, $ro->uri->query);
        }

        $declared = implode(',', array_map(static fn (Link $annotation): string => $annotation->rel, $annotations));

        throw new LinkRelException(sprintf('rel:%s class:%s method:on%s declared:%s', $link->key, $current::class, ucfirst($from->value), $declared), 500);
    }

    /** Follows a crawl link with GET, as the resource client does */
    public function crawl(LinkType $link, ResourceObject $current, Method $from): ResourceObject
    {
        $linkCrawler = $this->linkCrawlerProvider->get();
        $isList = $linkCrawler->isList($current->body);
        /** @var list<array<string, mixed>> $bodyList */
        $bodyList = $isList ? (array) $current->body : [$current->body];
        $linkCrawler->crawl($this->annotations($current, $from), $link, $bodyList);
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
