<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Resource\Page;

use BEAR\Resource\ResourceObject;

class Visit extends ResourceObject
{
    public function onGet(): static
    {
        $this->headers['Set-Cookie'] = 'visited=1; Path=/';
        $this->body = ['visited' => isset($_COOKIE['visited'])];

        return $this;
    }
}
