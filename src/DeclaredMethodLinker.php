<?php

declare(strict_types=1);

namespace BEAR\Dev;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\Code;
use BEAR\Resource\FactoryInterface;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\LinkCrawlerInterface;
use BEAR\Resource\LinkerInterface;
use BEAR\Resource\LinkType;
use BEAR\Resource\ResourceObject;
use Override;
use Ray\Di\Di\Set;
use Ray\Di\ProviderInterface;

/**
 * Follows the links of a request as a client does: each with the method its #[Link] declares, and the Location of a
 * 303 or a 201 with GET
 *
 * Bound for LinkerInterface in a test, in place of the resource client's linker, which follows every link with GET.
 * An #[Embed] src is followed the same way, so a link in it that is not GET runs.
 */
final class DeclaredMethodLinker implements LinkerInterface
{
    private DeclaredLinks $links;
    private LocationFollower $located;

    /** @param ProviderInterface<LinkCrawlerInterface> $linkCrawlerProvider */
    public function __construct(
        private readonly InvokerInterface $invoker,
        FactoryInterface $factory,
        #[Set(LinkCrawlerInterface::class)]
        ProviderInterface $linkCrawlerProvider,
    ) {
        $this->links = new DeclaredLinks($invoker, $factory, $linkCrawlerProvider);
        $this->located = new LocationFollower($invoker, $factory);
    }

    #[Override]
    public function invoke(AbstractRequest $request): ResourceObject
    {
        $this->invoker->invoke($request);
        [$current, $method] = ($this->located)(clone $request->resourceObject, $request->method);
        if ($current->code >= Code::BAD_REQUEST) {
            return $current;
        }

        foreach ($request->links as $link) {
            if ($link->type === LinkType::CRAWL_LINK) {
                $current = $this->links->crawl($link, $current, $method);

                continue;
            }

            $linkedRequest = $this->links->request($link, $current, $method);
            [$linked, $linkedMethod] = ($this->located)($this->invoker->invoke($linkedRequest), $linkedRequest->method);
            if ($link->type === LinkType::SELF_LINK) {
                $current = $linked;
                if ($current->code >= Code::BAD_REQUEST) {
                    return $current;
                }

                // The next link is read from the method the linked resource was requested with
                $method = $linkedMethod;

                continue;
            }

            // new: the request above read the body as an array
            $body = (array) $current->body;
            $body[$link->key] = $linked->body;
            $current->body = $body;
        }

        return $current;
    }
}
