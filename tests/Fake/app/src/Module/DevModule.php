<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Module;

use BEAR\Dev\DevInvoker;
use BEAR\Dev\Halo\HaloModule;

use BEAR\Dev\Halo\HaloRenderer;
use BEAR\QiqModule\QiqRenderer;
use BEAR\Resource\Invoker;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\JsonRenderer;
use BEAR\Resource\RenderInterface;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

class DevModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->install(new HaloModule($this));
    }
}
