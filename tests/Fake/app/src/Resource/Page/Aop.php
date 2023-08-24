<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Resource\Page;

use BEAR\Resource\ResourceObject;

class Aop extends ResourceObject
{
    public function onGet()
    {
        $this->body = ['method' => __FUNCTION__];

        return $this;
    }
}
