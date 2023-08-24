<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Resource\App;

use BEAR\Resource\ResourceObject;
class Ja extends ResourceObject
{
    public $body = [
        'greeting' => 'Konichiwa'
    ];

    public function onGet(): static
    {
        return $this;
    }
}
