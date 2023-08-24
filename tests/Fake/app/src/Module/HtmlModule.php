<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Module;

use BEAR\Dotenv\Dotenv;
use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;

use BEAR\QiqModule\QiqModule;
use Ray\Di\AbstractModule;
use function dirname;

class HtmlModule extends AbstractAppModule
{
    protected function configure(): void
    {
        $this->install(new QiqModule($this->appMeta->appDir . '/var/qiq/template'));
    }
}
