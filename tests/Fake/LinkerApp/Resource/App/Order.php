<?php

declare(strict_types=1);

namespace BEAR\Dev\LinkerApp\Resource\App;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\Code;
use BEAR\Resource\ResourceObject;

class Order extends ResourceObject
{
    public function onGet(int $id): static
    {
        $this->body = ['id' => $id, 'drink' => 'latte'];

        return $this;
    }

    #[Link(rel: 'payment', href: 'app://self/payment{?id}', method: 'put')]
    #[Link(rel: 'drink', href: 'app://self/drink/{drink}')]
    public function onPost(string $drink): static
    {
        $this->code = Code::CREATED;
        $this->body = ['id' => 7, 'drink' => $drink];

        return $this;
    }
}
