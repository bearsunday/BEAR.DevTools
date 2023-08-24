<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Module;

use BEAR\Package\PackageModule;

use MyVendor\MyProject\Resource\Page\Aop;
use Ray\Aop\NullInterceptor;
use Ray\Di\AbstractModule;
use function dirname;

class AppModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->bindInterceptor(
            $this->matcher->subclassesOf(Aop::class),
            $this->matcher->startsWith('on'),
            [NullInterceptor::class]
        );
        $this->install(new PackageModule());
    }
}
