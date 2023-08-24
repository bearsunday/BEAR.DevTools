<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Module;

use BEAR\Package\AbstractAppModule;

class TwigModule extends AbstractAppModule
{
    protected function configure(): void
    {
        $this->install(new \Madapaja\TwigModule\TwigModule());
    }
}
