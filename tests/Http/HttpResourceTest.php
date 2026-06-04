<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\Http\Exception\HalLinkNotFoundException;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri;
use LogicException;
use PHPUnit\Framework\TestCase;

use function assert;
use function dirname;
use function file_exists;

class HttpResourceTest extends TestCase
{
    /** @var HttpResource $resource */
    private $resource;

    public function setUp(): void
    {
        $index = dirname(__DIR__) . '/Fake/app/public/index.php';
        assert(file_exists($index));
        $this->resource = new HttpResource('127.0.0.1:8080', $index, __DIR__ . '/log');
    }

    public function testOnGet(): void
    {
        $ro = $this->resource->get('http://127.0.0.1:8080/');
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onGet"', $ro->view);
    }

    public function testOnPost(): void
    {
        $ro = $this->resource->post('/');
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }

    public function testOnPut(): void
    {
        $ro = $this->resource->put('/');
        $this->assertSame(200, $ro->code);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPut"', $ro->view);
    }

    public function testOnPatch(): void
    {
        $ro = $this->resource->patch('/');
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPatch"', $ro->view);
    }

    public function testOnDelete(): void
    {
        $ro = $this->resource->delete('/');
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onDelete"', $ro->view);
    }

    public function testStdErrLog(): void
    {
        $index = dirname(__DIR__) . '/Fake/app/public/index.php';
        $resource = new HttpResource('127.0.0.1:8080', $index);
        $ro = $resource->get('http://127.0.0.1:8080/');
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onGet"', $ro->view);
    }

    public function testHrefFollowsHalLinkWithGet(): void
    {
        $index = __DIR__ . '/index.php';
        assert(file_exists($index));
        $resource = new HttpResource('127.0.0.1:8081', $index, __DIR__ . '/log');

        $ro = $resource->get('/');
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"_links"', $ro->view);

        $next = $resource->href('next', [], $ro);
        $this->assertSame(200, $next->code);
        $this->assertStringContainsString('"page": "linked"', $next->view);
        $this->assertFileExists(__DIR__ . '/log/test-href-follows-hal-link-with-get.log');
    }

    public function testWorkflowFollowUsesHalHrefGet(): void
    {
        $index = __DIR__ . '/index.php';
        assert(file_exists($index));
        $resource = new HttpResource('127.0.0.1:8081', $index, __DIR__ . '/log');
        $ro = $resource->get('/');

        $workflow = new AbstractWorkflowTestHarness('runTest');
        $workflow->setResource($resource);

        $next = $workflow->followPublic($ro, 'next');
        $this->assertSame(200, $next->code);
        $this->assertStringContainsString('"page": "linked"', $next->view);
    }

    public function testHrefThrowsHalLinkNotFoundExceptionForMissingHalLink(): void
    {
        $index = __DIR__ . '/index.php';
        assert(file_exists($index));
        $resource = new HttpResource('127.0.0.1:8081', $index, __DIR__ . '/log');
        $ro = $resource->get('/');

        $this->expectException(HalLinkNotFoundException::class);
        $resource->href('missing', [], $ro);
    }

    public function testCreateResponseKeepsSingleLineJsonBody(): void
    {
        $uri = new Uri('http://127.0.0.1:8080/');
        $ro = (new CreateResponse())($uri, [
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            '',
            '{"method":"onGet"}',
        ]);

        $this->assertSame('{"method":"onGet"}', $ro->view);
        $this->assertSame('onGet', $ro->body['method']);
    }

    public function testPostWithSpecialCharactersSingleQuote(): void
    {
        $ro = $this->resource->post('/', ['data' => ['command' => "' AND 1=CONVERT(int, @@version)--"]]);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }

    public function testPostWithSpecialCharactersDoubleQuote(): void
    {
        $ro = $this->resource->post('/', ['data' => ['test' => 'value with "double quotes"']]);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }

    public function testPostWithSpecialCharactersBrackets(): void
    {
        $ro = $this->resource->post('/', ['data' => ['html' => '<script>alert("xss")</script>']]);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }

    public function testPostWithSpecialCharactersParentheses(): void
    {
        $ro = $this->resource->post('/', ['data' => ['sql' => 'SELECT * FROM users WHERE id = (1)']]);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }

    public function testPostWithMixedSpecialCharacters(): void
    {
        $ro = $this->resource->post('/', [
            'data' => ['complex' => 'It\'s a "test" with <brackets> and (parentheses) & special $chars'],
        ]);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }
}

final class AbstractWorkflowTestHarness extends AbstractWorkflowTest
{
    public function setResource(ResourceInterface $resource): void
    {
        $this->resource = $resource;
    }

    public function followPublic(ResourceObject $response, string $rel): ResourceObject
    {
        return $this->follow($response, $rel);
    }

    protected function newResource(): ResourceInterface
    {
        throw new LogicException();
    }
}
