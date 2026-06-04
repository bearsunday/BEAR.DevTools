<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Resource\Page;

use BEAR\Resource\ResourceObject;

class Linked extends ResourceObject
{
    public function onGet(): static
    {
        $this->body = ['method' => __FUNCTION__, 'page' => 'linked'];

        return $this;
    }
}
