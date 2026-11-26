<?php

declare(strict_types=1);

namespace BEAR\Dev\LinkerApp\Resource\App\Drink;

use BEAR\Resource\ResourceObject;

class Latte extends ResourceObject
{
    public function onGet(): static
    {
        $this->body = ['name' => 'latte'];

        return $this;
    }
}
