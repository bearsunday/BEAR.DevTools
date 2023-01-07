<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Module;

use BEAR\Dotenv\Dotenv;
use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;

use Ray\Di\AbstractModule;
use function dirname;

class AppModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->install(new PackageModule());
    }
}
