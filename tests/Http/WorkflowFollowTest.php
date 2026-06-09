<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Resource\ResourceInterface;

use function assert;
use function file_exists;

class WorkflowFollowTest extends AbstractWorkflowTest
{
    private const HOST = '127.0.0.1:18082';

    protected function newResource(): ResourceInterface
    {
        $index = __DIR__ . '/index.php';
        assert(file_exists($index));

        return new HttpResource(self::HOST, $index, __DIR__ . '/log');
    }

    public function testFollowUsesHalHrefGet(): void
    {
        $ro = $this->resource->get('/');
        $next = $this->follow($ro, 'next');
        $this->assertSame(200, $next->code);
        $this->assertStringContainsString('"page": "linked"', $next->view);
    }

    public function testLinkHrefResolvesRelWithoutFollowing(): void
    {
        $ro = $this->resource->get('/');

        $this->assertSame('page://self/linked', $this->linkHref($ro, 'next'));
    }

    public function testBodyStringReturnsTypedBodyValue(): void
    {
        $ro = $this->resource->get('/linked');

        $this->assertSame('linked', $this->bodyString($ro, 'page'));
    }
}
