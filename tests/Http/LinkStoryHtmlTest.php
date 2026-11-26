<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Resource\Exception\LinkException;
use BEAR\Resource\ResourceInterface;
use PHPUnit\Framework\Attributes\DataProvider;

use function dirname;

/** The stories followed over HTTP, where each link is read from an HTML anchor or form */
final class LinkStoryHtmlTest extends AbstractLinkStoryTest
{
    protected function newResource(): ResourceInterface
    {
        return new HttpResource('127.0.0.1:18482', dirname(__DIR__) . '/Fake/app/public-twig/index.php', __DIR__ . '/log');
    }

    /** @return array<string, array{string}> */
    public static function methodOverrideProvider(): array
    {
        return [
            'a hidden input' => ['doUpdate'],
            'the query of the action' => ['doDelete'],
        ];
    }

    #[DataProvider('methodOverrideProvider')]
    public function testAFormThatOverridesItsMethodIsNotSubmitted(string $rel): void
    {
        $this->expectException(LinkException::class);

        $this->resource->get("/form#link=self:{$rel}");
    }
}
