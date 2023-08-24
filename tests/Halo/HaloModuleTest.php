<?php

declare(strict_types=1);

namespace BEAR\Dev\Halo;

use BEAR\Resource\ResourceInterface;
use MyVendor\MyProject\Injector;
use PHPUnit\Framework\TestCase;

class HaloModuleTest extends TestCase
{
    private ResourceInterface $resource;

    protected function setUp(): void
    {
        $injector = Injector::getInstance('dev-app');
        $this->resource = $injector->getInstance(ResourceInterface::class);
    }

    /** @return array<array<string>> */
    public function pageProvider(): array
    {
        return [
            ['page://self/'],
            ['page://self/aop'],
        ];
    }

    /**
     * @dataProvider pageProvider
     */
    public function testModule(string $uri): void
    {
        $ro = $this->resource->get($uri);
        $view = (string) $ro;
        $this->assertStringContainsString('<!-- resource:page://self/', $view);
        $this->assertStringContainsString('<!-- resource_tab_end -->', $view);
    }
}
