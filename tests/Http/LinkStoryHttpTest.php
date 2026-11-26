<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Resource\ResourceInterface;
use LogicException;

/** The stories followed over HTTP, where each link is read from the HAL `_links` */
final class LinkStoryHttpTest extends AbstractApiLinkStoryTest
{
    protected function newResource(): ResourceInterface
    {
        return new HttpResource('127.0.0.1:18481', __DIR__ . '/index.php', __DIR__ . '/log');
    }

    public function testACrawlLinkIsRefusedBeforeAnyRequest(): void
    {
        $this->expectException(LogicException::class);

        $this->resource->post('/item?name=koriym#link=crawl:tree');
    }
}
