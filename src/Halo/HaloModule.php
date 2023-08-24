<?php

declare(strict_types=1);

namespace BEAR\Dev\Halo;

use BEAR\Dev\DevInvoker;
use BEAR\Resource\Invoker;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\RenderInterface;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

final class HaloModule extends AbstractModule
{
    public function __construct(AbstractModule $module)
    {
        $module->rename(RenderInterface::class, 'original');
        parent::__construct($module);
    }

    protected function configure(): void
    {
        $this->bind(InvokerInterface::class)->annotatedWith('original')->to(Invoker::class);
        $this->bind(InvokerInterface::class)->to(DevInvoker::class);
        $this->bind(RenderInterface::class)->to(HaloRenderer::class)->in(Scope::SINGLETON);
        $this->bind(TemplateLocator::class);
    }
}
