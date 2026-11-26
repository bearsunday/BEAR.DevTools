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
- Cookies kept across the requests of one `HttpResource`, as a browser keeps them
- Request/response capture for testing workflows

```php
use BEAR\Dev\Http\HttpResource;
use Koriym\FileUpload\FileUpload;

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

// A query that carries a Koriym\FileUpload\FileUpload is sent as multipart/form-data
$ro = $resource->post('/avatar', ['image' => FileUpload::fromFile(__DIR__ . '/avatar.png')]);
```

The fourth argument holds headers sent with every request:

```php
$resource = new HttpResource('127.0.0.1:8080', '/path/to/public/index.php', __DIR__ . '/log', ['Sec-Fetch-Site' => 'same-origin']);
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

`AbstractWorkflowTest` is the base contract for rel-driven workflow tests. Write
the workflow once against `ResourceInterface`, then run the same scenario over
HTTP by extending the concrete workflow test and overriding only `newResource()`
with `HttpResource`.

```php
use BEAR\Dev\Http\AbstractWorkflowTest;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use MyVendor\MyProject\Injector;

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

    /** @depends testIndex */
    public function testNext(ResourceObject $response): ResourceObject
    {
        return $this->follow($response, 'next');
    }
}
```

```php
use BEAR\Dev\Http\HttpResource;
use BEAR\Resource\ResourceInterface;
use MyVendor\MyProject\Hypermedia\WorkflowTest as Workflow;

class WorkflowTest extends Workflow
{
    protected function newResource(): ResourceInterface
    {
        return new HttpResource('127.0.0.1:8088', __DIR__ . '/index.php', __DIR__ . '/log');
    }
}
```

A request URI can carry the links to follow in a `#link=` fragment, as bear/resource reads it. The resource client
follows every link with GET; a test that steps through a workflow follows each link with the method its `#[Link]`
declares, and the `Location` of a 303 or a 201, by binding `DeclaredMethodLinker`:

```php
use BEAR\Dev\DeclaredMethodLinker;
use BEAR\Resource\LinkerInterface;

$this->bind(LinkerInterface::class)->to(DeclaredMethodLinker::class); // in the test context module
```

HTTP does not send a fragment, so `HttpResource` follows the links itself in the same way, and the same test runs in
process and over HTTP:

```php
public function testCreateAndUpdate(): void
{
    // POST doCreate and follow its 201 Location, follow goForm, PUT doUpdate and follow its 303 Location
    $item = $this->resource->get('/form#link=self:doCreate?name=koriym,self:goForm,self:doUpdate?id=3');

    $this->assertSame(3, $this->bodyValue($item, 'id'));
}
```

`DeclaredMethodLinker` also follows the links of an `#[Embed]` src, so bind it only where a link that is not GET may run.

`HttpResource` finds a rel and its method in the HAL `_links`, then in the HTML, then in the `Link` header (its `method`
parameter). In the HTML, an `<a>`, `<area>` or `<link>` is a GET, and a `<form>` is a POST when its `method` is `post`
and a GET otherwise, as a browser submits it: the affordances `HtmlLinkAuditor` checks against the `#[Link]`
declarations. A form needs an `action`. HTML has no PUT or DELETE, so a `#[Link]` that is not a GET is a POST form; a
POST that carries `_method`, as a hidden input or in the query of the action, throws `LinkException`, and
`HtmlLinkAuditor` reports such a form as `method-override`.

A form is sent with the hidden inputs inside it that have no `disabled` attribute, so a CSRF token the page carries goes
with it, and the link query is overlaid on them; the values are sent as JSON. An input that names its form with a `form`
attribute from outside, and a `<fieldset disabled>`, are not read. A link query fills the query of the href the
representation gives, not a variable already expanded into its path. Only the path and query of a URI or an href go to
the server, so `page://self/item` and `//other.example/item` both request `/item`. Cookies are kept across the requests
of one `HttpResource`. A crawl link is not followed over HTTP.

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
