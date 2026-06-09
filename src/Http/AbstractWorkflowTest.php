<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Resource\Code;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use PHPUnit\Framework\TestCase;

use function sprintf;
use function str_starts_with;
use function strtolower;

/**
 * Base contract for transport-agnostic hypermedia workflow tests.
 *
 * A concrete workflow test writes the scenario once against ResourceInterface.
 * An HTTP workflow test can then extend that concrete test and override only
 * newResource() with HttpResource, proving the same rel-driven scenario across
 * in-process and HTTP transports.
 */
abstract class AbstractWorkflowTest extends TestCase
{
    /** @var array<class-string, ResourceInterface> */
    private static array $resources = [];
    protected ResourceInterface $resource;

    protected function setUp(): void
    {
        $class = static::class;
        $this->resource = self::$resources[$class] ??= $this->newResource();
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

    /**
     * Resolves a link relation without issuing a request.
     *
     * Use this for unsafe transitions (for example POST/PUT/DELETE) where the
     * workflow needs the target URI but must choose the HTTP method explicitly.
     */
    protected function linkHref(ResourceObject $response, string $rel): string
    {
        if ($response->view === null) {
            // Rendering materializes representation-driven headers such as Link.
            (string) $response;
        }

        $href = (new HrefExtractor())->href($rel, $response);
        $this->assertIsString($href, sprintf('Link rel `%s` should be present in the representation.', $rel));

        return $this->resourceUriForLocation($href);
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

    protected function bodyString(ResourceObject $response, string $key): string
    {
        $value = $this->bodyValue($response, $key);
        $this->assertIsString($value, sprintf('Expected body key `%s` to be a string.', $key));

        return $value;
    }

    protected function header(ResourceObject $response, string $name): string|null
    {
        foreach ($response->headers as $header => $value) {
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
