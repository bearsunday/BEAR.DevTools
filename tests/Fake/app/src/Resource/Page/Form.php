<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Resource\Page;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

class Form extends ResourceObject
{
    #[Link(rel: 'doCreate', href: '/item', method: 'post')]
    #[Link(rel: 'doUpdate', href: '/item{?id}', method: 'put')]
    #[Link(rel: 'doRedirectTwice', href: '/redirect', method: 'post')]
    #[Link(rel: 'doLeave', href: '/redirect', method: 'put')]
    #[Link(rel: 'doLoseLocation', href: '/redirect', method: 'delete')]
    public function onGet(): static
    {
        $this->body = ['page' => 'form'];

        return $this;
    }
}
