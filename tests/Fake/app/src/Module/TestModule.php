<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Module;

use BEAR\Dev\DeclaredMethodLinker;
use BEAR\Resource\LinkerInterface;
use Ray\Di\AbstractModule;

/** A workflow test follows each link with the method its #[Link] declares */
class TestModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->bind(LinkerInterface::class)->to(DeclaredMethodLinker::class);
    }
}
