<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Hypermedia;

use BEAR\Dev\Http\AbstractWorkflowTest;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use MyVendor\MyProject\Injector;
use PHPUnit\Framework\Attributes\Depends;

use function assert;

class WorkflowTest extends AbstractWorkflowTest
{
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

    #[Depends('testIndex')]
    public function testNext(ResourceObject $response): ResourceObject
    {
        return $this->follow($response, 'next');
    }

    /** POST a link and follow its 201 Location, then follow a link, then PUT a link and follow its 303 Location */
    public function testLinksInTheUri(): void
    {
        $item = $this->resource->get('/form#link=self:doCreate?name=koriym,self:goForm,self:doUpdate?id=3');

        $this->assertSame(200, $item->code);
        $this->assertSame(3, $this->bodyValue($item, 'id'));
    }
}
