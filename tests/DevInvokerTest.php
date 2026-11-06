<?php

declare(strict_types=1);

namespace BEAR\Dev;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\NullResourceObject;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DevInvokerTest extends TestCase
{
    public function testHeadersAreSetEvenWhenInvocationThrows(): void
    {
        $ro = new NullResourceObject();
        $invoker = new DevInvoker(new class implements InvokerInterface {
            public function invoke(AbstractRequest $request): ResourceObject
            {
                throw new RuntimeException('boom');
            }
        });

        try {
            $invoker->invoke(new Request($invoker, $ro));
            $this->fail('The exception should propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertArrayHasKey(DevInvoker::HEADER_EXECUTION_TIME, $ro->headers);
        $this->assertArrayHasKey(DevInvoker::HEADER_MEMORY_USAGE, $ro->headers);
    }

    /** @requires extension xhprof */
    public function testOnlyOutermostRequestIsProfiled(): void
    {
        $outer = new NullResourceObject();
        $inner = new NullResourceObject();
        $devInvoker = null;
        $original = new class ($inner, static function () use (&$devInvoker): DevInvoker {
            return $devInvoker;
        }) implements InvokerInterface {
            /** @param callable(): DevInvoker $devInvoker */
            public function __construct(
                private ResourceObject $inner,
                private $devInvoker,
            ) {
            }

            public function invoke(AbstractRequest $request): ResourceObject
            {
                if ($request->resourceObject !== $this->inner) {
                    // An embedded resource is requested while the outer one runs.
                    $devInvoker = ($this->devInvoker)();
                    $devInvoker->invoke(new Request($devInvoker, $this->inner));
                }

                return $request->resourceObject;
            }
        };
        $devInvoker = new DevInvoker($original);

        $devInvoker->invoke(new Request($devInvoker, $outer));

        $this->assertArrayHasKey(DevInvoker::HEADER_PROFILE_ID, $outer->headers);
        $this->assertArrayNotHasKey(DevInvoker::HEADER_PROFILE_ID, $inner->headers);
    }
}
