# BEAR.DevTools

## Halo module

A frame, called a halo, appears around the HTML representation of the resource.
The halo identifies the resource being rendered and provides tools about the resource.

The tools in the halo provide information about the resource, such as its status (Status), its representation (View), the interceptor applied to it, and so on. It also provides links to editors to resource classes and resource templates.

```php
class DevModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->install(new HaloModule($this));
    }
}
```

## HttpResource client

`HttpResource` starts a local server and becomes an HTTP client.

```php
$resource =  new HttpResource('127.0.0.1:8099', '/path/to/index.php',  '/path/to/curl.log');
$ro = $resource->get('/');
assert($ro->code === 200);
```

