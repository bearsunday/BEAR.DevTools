# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

BEAR.DevTools is a development package for the BEAR.Sunday PHP framework that provides essential debugging and testing utilities. This package is designed to be installed as a development dependency and provides visual debugging interfaces and HTTP testing capabilities.

## Core Components

### 1. Halo Module - Visual Resource Inspector

The Halo module creates a visual debugging interface around HTML representations of BEAR.Sunday resources. This concept is inspired by the Seaside Smalltalk web framework.

**Key Features:**
- Visual frames around rendered resources
- Resource metadata display (status, representation, interceptors)
- Direct links to resource class and template editors
- Performance profiling integration
- Real-time resource state inspection

**Usage:**
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

### 2. HttpResource Client - HTTP Testing Utility

HttpResource provides an HTTP client that automatically starts a built-in PHP server for testing BEAR.Sunday applications.

**Key Features:**
- Automatic local server startup using koriym/php-server
- Full HTTP method support (GET, POST, PUT, DELETE, PATCH)
- Comprehensive request/response logging
- Curl command logging for reproducible tests
- Integration with BEAR.Sunday resource objects

**Usage:**
```php
use BEAR\Dev\Http\HttpResource;

$resource = new HttpResource('127.0.0.1:8099', '/path/to/index.php', '/path/to/curl.log');
$ro = $resource->get('/users');
$ro = $resource->post('/users', ['name' => 'John', 'email' => 'john@example.com']);
```

## Architecture

### Dependencies
- **Core**: PHP 8.0+, BEAR.Sunday framework components
- **HTTP Server**: koriym/php-server for built-in server functionality
- **Database**: aura/sql v3-v6 for database connectivity (including PHP 8.4 support)
- **Profiling**: XHProf integration (optional, via require-dev)

### File Structure
```
src/
├── Halo/           # Visual debugging interface
│   ├── HaloModule.php
│   ├── HaloRenderer.php
│   └── TemplateLocator.php
├── Http/           # HTTP testing utilities
│   ├── HttpResource.php
│   └── CreateResponse.php
├── DevInvoker.php  # Development-specific resource invoker
├── QueryMerger.php # Query parameter handling
└── Uri.php         # URI utilities

src-deprecated/     # Legacy components for backward compatibility
template/          # Test templates and examples
tests/            # Comprehensive test suite
```

## Integration with BEAR.Sunday

BEAR.DevTools integrates seamlessly with the BEAR.Sunday ecosystem:

1. **Resource-Oriented**: Works with BEAR.Sunday's resource-oriented architecture
2. **Dependency Injection**: Uses Ray.Di for dependency injection
3. **AOP Integration**: Leverages AOP interceptors for debugging
4. **HAL+JSON**: Supports hypermedia API development and testing
5. **Context-Aware**: Works with different application contexts (dev, test, etc.)

## Development Commands

### Testing
```bash
# Run all tests
./vendor/bin/phpunit

# Run tests with coverage
php -dzend_extension=xdebug.so ./vendor/bin/phpunit --coverage-text --coverage-html=build/coverage

# Run HTTP workflow tests specifically
./vendor/bin/phpunit tests/Http/WorkflowTest.php
```

### Code Quality
```bash
# Static analysis
./vendor/bin/phpstan analyse -c phpstan.neon
psalm --show-info=true

# Code style
./vendor/bin/phpcs --standard=./phpcs.xml src tests
./vendor/bin/phpcbf src tests  # Fix automatically
```

### Development Workflow

When working on this package:

1. **Testing HTTP Features**: Use the HttpResource class to test server startup and HTTP client functionality
2. **Halo Development**: Test visual debugging features in a BEAR.Sunday application context
3. **Dependency Updates**: Ensure compatibility with all supported aura/sql versions (v3-v6)
4. **XHProf Integration**: Test profiling features when XHProf extension is available

### Common Issues and Solutions

1. **Server Startup**: HttpResource automatically manages PHP server lifecycle using koriym/php-server
2. **Port Conflicts**: Use different ports for concurrent testing (default: varies by test)
3. **Log File Permissions**: Ensure write permissions for HTTP access log files
4. **XHProf Dependency**: XHProf is optional (require-dev) - tests should handle its absence gracefully

## Testing Patterns

### HTTP Workflow Testing
```php
class WorkflowTest extends TestCase
{
    protected ResourceInterface $resource;
    
    public function testUserCreationWorkflow(): void
    {
        // Test resource access
        $index = $this->resource->get('/');
        $this->assertSame(200, $index->code);
        
        // Follow HAL links
        $usersLink = json_decode((string) $index)->_links->users->href;
        $users = $this->resource->get($usersLink);
        
        // Test resource creation
        $postLink = json_decode((string) $users)->_links->{'create-user'}->href;
        $newUser = $this->resource->post($postLink, ['name' => 'Test User']);
        $this->assertSame(201, $newUser->code);
    }
}
```

## Best Practices

1. **Development Only**: This package should only be used in development/testing contexts
2. **Server Management**: HttpResource handles server lifecycle - don't manually manage PHP servers
3. **Log Files**: Configure appropriate locations for HTTP access logs
4. **Compatibility**: Maintain support for PHP 8.0+ and all current BEAR.Sunday versions
5. **XHProf Optional**: All profiling features should work without XHProf extension

## Release Process

When preparing releases:

1. Update CHANGELOG.md with version-specific changes
2. Ensure all tests pass with latest dependencies
3. Verify PHP 8.4 compatibility (especially aura/sql v6)
4. Update documentation examples if API changes
5. Test XHProf integration when available