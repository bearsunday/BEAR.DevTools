<?php

declare(strict_types=1);

namespace BEAR\Dev\LinkerApp\Resource\App;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

class Form extends ResourceObject
{
    #[Link(rel: 'create', href: 'app://self/item', method: 'post')]
    #[Link(rel: 'createInUpperCase', href: 'app://self/item', method: 'POST')]
    #[Link(rel: 'update', href: 'app://self/item{?id}', method: 'put')]
    #[Link(rel: 'redirectTwice', href: 'app://self/redirect', method: 'post')]
    #[Link(rel: 'leave', href: 'app://self/redirect', method: 'put')]
    #[Link(rel: 'noLocation', href: 'app://self/redirect', method: 'delete')]
    #[Link(rel: 'lowerCaseLocation', href: 'app://self/redirect', method: 'patch')]
    #[Link(rel: 'networkPath', href: 'app://self/network-path', method: 'post')]
    #[Link(rel: 'fail', href: 'app://self/item', method: 'delete')]
    public function onGet(): static
    {
        $this->body = ['id' => 7];

        return $this;
    }
}
