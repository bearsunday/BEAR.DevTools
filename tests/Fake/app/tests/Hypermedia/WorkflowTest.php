<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Hypermedia;

use BEAR\Dev\Http\WorkflowTestTrait;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use MyVendor\MyProject\Injector;
use PHPUnit\Framework\TestCase;

use function assert;

class WorkflowTest extends TestCase
{
    use WorkflowTestTrait;

    protected function newResource(): ResourceInterface
    {
        $resource = Injector::getInstance('app')->getInstance(ResourceInterface::class);
        assert($resource instanceof ResourceInterface);

        return $resource;
    }

    public function testIndex(): ResourceObject
    {
        $index = $this->resource->get('/index');
        $this->assertSame(200, $index->code);

        return $index;
    }

    /**
     * @depends testIndex
     */
    public function testNext(ResourceObject $response): ResourceObject
    {
        return $this->follow($response, 'next');
    }
}
