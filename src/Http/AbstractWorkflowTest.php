<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Resource\Code;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use PHPUnit\Framework\TestCase;

use function is_string;
use function str_starts_with;
use function strtolower;

abstract class AbstractWorkflowTest extends TestCase
{
    /** @var array<class-string, ResourceInterface> */
    private static array $resources = [];
    protected ResourceInterface $resource;

    protected function setUp(): void
    {
        $this->resource = self::$resources[static::class] ??= $this->newResource();
    }

    public static function tearDownAfterClass(): void
    {
        unset(self::$resources[static::class]);
    }

    abstract protected function newResource(): ResourceInterface;

    /**
     * Follows a safe HAL/Resource link with GET.
     *
     * Unsafe `do*` transitions call post/put/delete directly in the workflow
     * step because HAL links do not carry an HTTP method.
     *
     * @param array<string, mixed> $query
     */
    protected function follow(ResourceObject $response, string $rel, array $query = []): ResourceObject
    {
        $next = $this->resource->href($rel, $query, $response);
        $this->assertSame(Code::OK, $next->code);

        return $next;
    }

    protected function followLocation(ResourceObject $response, string|null $expectedLocation = null): ResourceObject
    {
        $location = $this->header($response, 'Location');
        $this->assertIsString($location);
        if ($expectedLocation !== null) {
            $this->assertSame($expectedLocation, $location);
        }

        $next = $this->resource->get($this->resourceUriForLocation($location));
        $this->assertSame(Code::OK, $next->code);

        return $next;
    }

    protected function bodyValue(ResourceObject $response, string $key): mixed
    {
        $body = $response->body;
        $this->assertIsArray($body);
        $this->assertArrayHasKey($key, $body);

        return $body[$key];
    }

    protected function header(ResourceObject $response, string $name): string|null
    {
        foreach ($response->headers as $header => $value) {
            if (! is_string($header) || ! is_string($value)) {
                continue;
            }

            if (strtolower($header) !== strtolower($name)) {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function resourceUriForLocation(string $location): string
    {
        if (str_starts_with($location, '/')) {
            return 'page://self' . $location;
        }

        return $location;
    }
}
