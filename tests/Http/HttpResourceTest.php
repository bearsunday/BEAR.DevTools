<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

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
        $this->resource = new HttpResource('127.0.0.1:8080', $index, __DIR__ . '/log/app.log');
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

    public function testPostWithSpecialCharactersSingleQuote(): void
    {
        $ro = $this->resource->post('/', ['data' => ["command" => "' AND 1=CONVERT(int, @@version)--"]]);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }

    public function testPostWithSpecialCharactersDoubleQuote(): void
    {
        $ro = $this->resource->post('/', ['data' => ["test" => 'value with "double quotes"']]);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }

    public function testPostWithSpecialCharactersBrackets(): void
    {
        $ro = $this->resource->post('/', ['data' => ["html" => '<script>alert("xss")</script>']]);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }

    public function testPostWithSpecialCharactersParentheses(): void
    {
        $ro = $this->resource->post('/', ['data' => ["sql" => 'SELECT * FROM users WHERE id = (1)']]);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }

    public function testPostWithMixedSpecialCharacters(): void
    {
        $ro = $this->resource->post('/', ['data' => [
            'complex' => 'It\'s a "test" with <brackets> and (parentheses) & special $chars',
        ]]);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }
}
