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
- HTTP request logging to file
- Full HTTP method support (GET, POST, PUT, DELETE, etc.)
- Request/response capture for testing workflows

```php
use BEAR\Dev\Http\HttpResource;

// Start server and create HTTP client
$resource = new HttpResource('127.0.0.1:8099', '/path/to/index.php', '/path/to/curl.log');

// Make HTTP requests
$ro = $resource->get('/users');
assert($ro->code === 200);

$ro = $resource->post('/users', ['name' => 'John', 'email' => 'john@example.com']);
assert($ro->code === 201);
```

#### HTTP Access Log

All HTTP requests made through `HttpResource` are automatically logged with full curl command equivalents:

```bash
curl -s -i 'http://127.0.0.1:8099/users'

HTTP/1.1 200 OK
Content-Type: application/hal+json
...
```

## Requirements

- PHP 8.0 or higher
- BEAR.Sunday framework

## Development

This package includes comprehensive development tools:

- **Code Quality**: PHPStan, Psalm, PHP_CodeSniffer
- **Testing**: PHPUnit with coverage reporting
- **Profiling**: XHProf integration (optional)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

