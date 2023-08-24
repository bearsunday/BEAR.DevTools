<?php

declare(strict_types=1);

namespace BEAR\Dev\Halo;

use BEAR\AppMeta\Meta;
use BEAR\Dev\FakeHalo;
use BEAR\Resource\NullRenderer;
use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceObject;
use PHPUnit\Framework\TestCase;
use Ray\Aop\NullInterceptor;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;

class HaloRendererTest extends TestCase
{
    public function testRender(): void
    {
        $originalRenderer = new class implements RenderInterface
        {
            public function render(ResourceObject $ro): string
            {
                return '<html>
    <head>
        <title>Greting</title>
    </head>
    <body><h1>Greeting</h1><p>Hello World!</p></body>
</html>';
            }
        };
        $renderer = new HaloRenderer($originalRenderer, new TemplateLocator(new Meta('MyVendor\MyProject')));
        $ro = (new Injector(new class extends AbstractModule{
            protected function configure(): void
            {
                $this->bindInterceptor(
                    $this->matcher->any(),
                    $this->matcher->any(),
                    [NullInterceptor::class]
                );
                $this->bind(FakeHalo::class);
                $this->bind(RenderInterface::class)->to(NullRenderer::class);
            }
        }))->getInstance(FakeHalo::class);
        $ro->uri = 'page://self/dummy';
        $view = $renderer->render($ro);
        $this->assertStringStartsWith('<html>', $view);
        $this->assertStringContainsString('<body><!-- resource:', $view);
    }
}
