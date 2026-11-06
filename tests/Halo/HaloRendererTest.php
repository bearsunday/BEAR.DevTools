<?php

declare(strict_types=1);

namespace BEAR\Dev\Halo;

use BEAR\AppMeta\Meta;
use BEAR\Dev\FakeHalo;
use BEAR\Resource\NullRenderer;
use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri;
use PHPUnit\Framework\TestCase;
use Ray\Aop\NullInterceptor;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;

class HaloRendererTest extends TestCase
{
    public function tearDown(): void
    {
        unset($_GET['halo']);

        parent::tearDown();
    }

    public function testNoHalo(): void
    {
        $renderer = new HaloRenderer(new NullRenderer(), new TemplateLocator(new Meta('MyVendor\MyProject')));
        $view = $renderer->render(new FakeHalo());
        $this->assertSame('', $view);
    }

    /** @return array{0:ResourceObject, 1:HaloRenderer} */
    public function testRender(): array
    {
        $_GET['halo'] = '1';
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
        $ro->uri = new Uri('page://self/dummy');
        $view = $renderer->render($ro);
        $this->assertStringStartsWith('<html>', $view);
        $this->assertStringContainsString('<body><!-- resource:', $view);

        return [$ro, $renderer];
    }

    /**
     * @param array{0:ResourceObject, 1:HaloRenderer} $roRenderer
     *
     * @depends testRender
     */
    public function testRenderWithHaloBodyString(array $roRenderer): void
    {
        [$ro, $renderer] = $roRenderer;
        $ro->body = 'Hello World!';
        $view = $renderer->render($ro);
        $this->assertStringContainsString('<p>Hello World!</p>', $view);
    }

    /**
     * @param array{0:ResourceObject, 1:HaloRenderer} $roRenderer
     *
     * @depends testRender
     */
    public function testRenderReturnsViewWithDevTools(array $roRenderer): void
    {
        [$ro, $renderer] = $roRenderer;
        $_GET['halo'] = '1';
        $view = $renderer->render($ro);

        $this->assertStringContainsString('<!-- BEAR.Sunday dev tools -->', $view);
        $this->assertSame($ro->view, $view);
    }

    /**
     * @param array{0:ResourceObject, 1:HaloRenderer} $roRenderer
     *
     * @depends testRender
     */
    public function testStateIsEscaped(array $roRenderer): void
    {
        [$ro, $renderer] = $roRenderer;
        $_GET['halo'] = '1';
        $ro->body = ['name' => '<script>alert(1)</script>'];
        $view = $renderer->render($ro);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $view);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $view);
    }

    /**
     * @param array{0:ResourceObject, 1:HaloRenderer} $roRenderer
     *
     * @depends testRender
     */
    public function testResourceUriIsEscaped(array $roRenderer): void
    {
        [$ro, $renderer] = $roRenderer;
        $_GET['halo'] = '1';
        $ro->uri = new Uri('page://self/dummy?q=1&x=2');
        $view = $renderer->render($ro);

        $this->assertStringNotContainsString('?q=1&x=2', $view);
        $this->assertStringContainsString('?q=1&amp;x=2', $view);
    }
}
