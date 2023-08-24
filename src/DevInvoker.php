<?php

declare(strict_types=1);

namespace BEAR\Dev;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;
use Ray\Aop\MethodInterceptor;
use Ray\Aop\WeavedInterface;
use Ray\Di\Di\Named;
use XHProfRuns_Default;

use function assert;
use function extension_loaded;
use function get_class;
use function json_encode;
use function memory_get_usage;
use function microtime;
use function property_exists;
use function sys_get_temp_dir;
use function xhprof_disable;
use function xhprof_enable;

use const XHPROF_FLAGS_CPU;
use const XHPROF_FLAGS_MEMORY;
use const XHPROF_FLAGS_NO_BUILTINS;

final class DevInvoker implements InvokerInterface
{
    public const HEADER_INTERCEPTORS = 'x-interceptors';

    public const HEADER_EXECUTION_TIME = 'x-execution-time';

    public const HEADER_MEMORY_USAGE = 'x-memory-usage';

    public const HEADER_PROFILE_ID = 'x-profile-id';

    public const HEADER_PARAMS = 'x-params';

    public const HEADER_QUERY = 'x-query';

    public function __construct(
        #[Named('original')]
        private InvokerInterface $invoker
    ) {
    }

    public function invoke(AbstractRequest $request): ResourceObject
    {
        $resource = $this->getRo($request);

        if ($request->method === Request::OPTIONS || $request->method === Request::HEAD) {
            return $this->invoker->invoke($request);
        }

        return $this->devInvoke($resource, $request);
    }

    private function devInvoke(ResourceObject $resource, AbstractRequest $request): ResourceObject
    {
        if (extension_loaded('xhprof')) {
            /** @psalm-suppress UndefinedConstant */
            xhprof_enable(XHPROF_FLAGS_NO_BUILTINS | XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY);
        }

        $resource->headers[self::HEADER_QUERY] = (string) json_encode($request->query);
        $time = microtime(true);
        $memory = memory_get_usage();

        $result = $this->invoker->invoke($request);

        // post process for log
        $resource->headers[self::HEADER_EXECUTION_TIME] = (string) (microtime(true) - $time);
        $resource->headers[self::HEADER_MEMORY_USAGE] = (string) (memory_get_usage() - $memory);
        if (extension_loaded('xhprof')) {
            $xhprof = xhprof_disable();
            $profileId = (new XHProfRuns_Default(sys_get_temp_dir()))->save_run($xhprof, 'resource');
            $resource->headers[self::HEADER_PROFILE_ID] = $profileId;
        }

        return $result;
    }

    private function getRo(AbstractRequest $request): ResourceObject
    {
        if (! $request->resourceObject instanceof WeavedInterface) {
            return $request->resourceObject;
        }

        assert(property_exists($request->resourceObject, 'bindings'));
        /** @psalm-suppress UndefinedPropertyFetch */
        $bind = $request->resourceObject->bindings;
        $interceptors = $this->getBindInfo($bind);
        $request->resourceObject->headers[self::HEADER_INTERCEPTORS] = (string) json_encode($interceptors);

        return $request->resourceObject;
    }

    /**
     * @param array<string, array<MethodInterceptor>> $bindgs
     *
     * @return array<string, array<string>>
     */
    public function getBindInfo(array $bindgs): array
    {
        $interceptorInfo = [];
        foreach ($bindgs as $method => $interceptors) {
            $interceptorNames = [];
            foreach ($interceptors as &$interceptor) {
                $interceptorNames[] = get_class($interceptor);
            }

            $interceptorInfo[$method] = $interceptorNames;
        }

        return $interceptorInfo;
    }
}
