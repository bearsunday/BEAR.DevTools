<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Resource\ResourceInterface;
use PHPUnit\Framework\TestCase;

use function assert;
use function file_exists;

class WorkflowFollowTest extends TestCase
{
    use WorkflowTestTrait;

    protected function newResource(): ResourceInterface
    {
        $index = __DIR__ . '/index.php';
        assert(file_exists($index));

        return new HttpResource('127.0.0.1:8081', $index, __DIR__ . '/log');
    }

    public function testFollowUsesHalHrefGet(): void
    {
        $ro = $this->resource->get('/');
        $next = $this->follow($ro, 'next');
        $this->assertSame(200, $next->code);
        $this->assertStringContainsString('"page": "linked"', $next->view);
    }
}
