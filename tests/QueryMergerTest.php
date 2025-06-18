<?php

declare(strict_types=1);

namespace BEAR\Dev;

use PHPUnit\Framework\TestCase;

class QueryMergerTest extends TestCase
{
    /** @var QueryMerger */
    private $queryMerger;

    protected function setUp(): void
    {
        $this->queryMerger = new QueryMerger();

        parent::setUp();
    }

    public function testSimplest(): void
    {
        $uri = ($this->queryMerger)('http://example.com/', ['id' => 1]);
        $this->assertSame('/', $uri->path);
        $this->assertSame(['id' => 1], $uri->query);
    }

    public function testUriHasQuery(): void
    {
        $uri = ($this->queryMerger)('http://example.com/?a=1', ['id' => '1']);
        $this->assertSame('/', $uri->path);
        $this->assertSame(['a' => '1', 'id' => '1'], $uri->query);
    }

    public function testQueryIsEmpty(): void
    {
        $uri = ($this->queryMerger)('http://example.com/?a=1', []);
        $this->assertSame('/', $uri->path);
        $this->assertSame(['a' => '1'], $uri->query);
    }
}
