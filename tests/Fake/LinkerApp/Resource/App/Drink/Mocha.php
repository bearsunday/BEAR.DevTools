<?php

declare(strict_types=1);

namespace BEAR\Dev\LinkerApp\Resource\App\Drink;

use BEAR\Resource\ResourceObject;

class Mocha extends ResourceObject
{
    public function onGet(): static
    {
        $this->body = ['name' => 'mocha'];

        return $this;
    }
}
