# BEAR.DevTools

Development tools and utilities for BEAR.Sunday framework applications.

[![Latest Stable Version](https://poser.pugx.org/bear/devtools/v/stable)](https://packagist.org/packages/bear/devtools)
[![Total Downloads](https://poser.pugx.org/bear/devtools/downloads)](https://packagist.org/packages/bear/devtools)
[![License](https://poser.pugx.org/bear/devtools/license)](https://packagist.org/packages/bear/devtools)

## Installation

```bash
composer require --dev bear/devtools
```

## Features

### Halo Module - Resource Development Inspector

The Halo module provides a visual development interface that appears around HTML representations of resources, offering detailed information about the resource being rendered.

> **Note**: The Halo concept is inspired by the [Seaside](http://www.seaside.st/) Smalltalk web framework, which pioneered this approach to visual web development debugging.

**Features:**
- Resource status and metadata display
- Interceptor chain visualization
- Direct links to resource class and template editors
- Request/response analysis
- Performance profiling integration

```php
use BEAR\Dev\Halo\HaloModule;
use Ray\Di\AbstractModule;

class DevModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->install(new HaloModule($this));
    }
}
```

### HttpResource Client - HTTP Testing Utility

`HttpResource` starts a built-in PHP server and provides an HTTP client interface for testing your BEAR.Sunday applications.

**Features:**
- Automatic local server startup
- HTTP request logging (to STDERR, a single file, or per-test files)
- Full HTTP method support (GET, POST, PUT, PATCH, DELETE)
- HAL link following with `href()`
- Request/response capture for testing workflows

```php
use BEAR\Dev\Http\HttpResource;

// Start the built-in server and create an HTTP client.
// The third argument is the log destination and is optional
// (defaults to 'php://stderr'):
//   - 'php://stderr'       : write logs to STDERR (default)
//   - '/path/to/file.log'  : write every request to a single file
//   - '/path/to/log'       : a directory; one '<test-name>.log' per test method
$resource = new HttpResource('127.0.0.1:8080', '/path/to/public/index.php', __DIR__ . '/log');

// Make HTTP requests
$ro = $resource->get('/users');
assert($ro->code === 200);

$ro = $resource->post('/users', ['name' => 'John', 'email' => 'john@example.com']);
assert($ro->code === 201);
```

#### Following HAL links

`href()` follows a HAL `_links` relation from a response with a `GET` request:

```php
$index = $resource->get('/');
// {"_links": {"next": {"href": "/users"}}}

$users = $resource->href('next', [], $index);
assert($users->code === 200);
```

A `HalLinkNotFoundException` is thrown when the relation or its `href` is missing.

#### HTTP Access Log

Each request is logged with its equivalent `curl` command followed by the raw response:

```bash
curl -s -i 'http://127.0.0.1:8080/users'

HTTP/1.1 200 OK
Content-Type: application/hal+json
...
```

### Workflow Testing

`WorkflowTestTrait` helps drive hypermedia workflow tests. Implement `newResource()`, then navigate with `follow()` (a safe `GET` via a HAL `_links` relation) and `followLocation()` (a `GET` of the `Location` header returned by an unsafe transition). The same test body can run in-process or over HTTP by changing what `newResource()` returns.

```php
use BEAR\Dev\Http\HttpResource;
use BEAR\Dev\Http\WorkflowTestTrait;
use BEAR\Resource\ResourceInterface;
use PHPUnit\Framework\TestCase;

final class WorkflowTest extends TestCase
{
    use WorkflowTestTrait;

    protected function newResource(): ResourceInterface
    {
        return new HttpResource('127.0.0.1:8080', __DIR__ . '/public/index.php', __DIR__ . '/log');
    }

    public function testWorkflow(): void
    {
        $index = $this->resource->get('/');
        $next = $this->follow($index, 'next');
        $this->assertSame(200, $next->code);
    }
}
```

## Requirements

- PHP 8.2 or higher
- ext-curl
- BEAR.Sunday framework

## Development

This package includes comprehensive development tools:

- **Code Quality**: PHPStan, Psalm, PHP_CodeSniffer
- **Testing**: PHPUnit with coverage reporting
- **Profiling**: XHProf integration (optional)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

