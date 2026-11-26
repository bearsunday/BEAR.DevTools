<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\LinkStories;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Throwable;

/** The stories that need PUT and the like, which an HTML form does not have, and the ones that cannot be followed */
abstract class AbstractApiLinkStoryTest extends AbstractLinkStoryTest
{
    /** @param array<string, string> $query */
    #[DataProviderExternal(LinkStories::class, 'apiOnly')]
    public function testTheApiStoryEndsWhereItSays(string $uri, string $path, array $query): void
    {
        $this->testTheStoryEndsWhereItSays($uri, $path, $query);
    }

    /** @param class-string<Throwable> $exception */
    #[DataProviderExternal(LinkStories::class, 'unfollowable')]
    public function testAStoryThatCannotBeFollowedThrows(string $uri, string $exception): void
    {
        $this->expectException($exception);

        $this->resource->get($uri);
    }

    public function testANewLinkAddsTheBodyOfTheLocatedResource(): void
    {
        $ro = $this->resource->get('/form#link=new:doCreate?name=koriym');

        $this->assertIsArray($ro->body);
        $this->assertSame(5, $ro->body['doCreate']['id'] ?? null);
    }
}
