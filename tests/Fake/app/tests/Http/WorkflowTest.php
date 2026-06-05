<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Http;

use BEAR\Dev\Http\HttpResource;
use BEAR\Resource\ResourceInterface;
use MyVendor\MyProject\Hypermedia\WorkflowTest as Workflow;

class WorkflowTest extends Workflow
{
    protected function newResource(): ResourceInterface
    {
        return new HttpResource(
            '127.0.0.1:8088',
            __DIR__ . '/index.php',
            __DIR__ . '/log',
        );
    }
}
