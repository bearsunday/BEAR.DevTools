<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\LinkStories;
use BEAR\Resource\ResourceInterface;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

/** The stories of LinkStories followed by the resource client a subclass gives */
abstract class AbstractLinkStoryTest extends TestCase
{
    protected ResourceInterface $resource;

    abstract protected function newResource(): ResourceInterface;

    protected function setUp(): void
    {
        $this->resource = $this->newResource();
    }

    /** @param array<string, string> $query */
    #[DataProviderExternal(LinkStories::class, 'anyRepresentation')]
    public function testTheStoryEndsWhereItSays(string $uri, string $path, array $query): void
    {
        $ro = $this->resource->get($uri);

        $this->assertSame([200, $path, $query], [$ro->code, $ro->uri->path, $ro->uri->query]);
    }
}
