<?php

declare(strict_types=1);

namespace BEAR\Dev\LinkerApp\Resource\App;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\Code;
use BEAR\Resource\ResourceObject;

class Payment extends ResourceObject
{
    #[Link(rel: 'order', href: 'app://self/order{?id}')]
    public function onPut(int $id, string $card): static
    {
        $this->code = Code::CREATED;
        $this->body = ['id' => $id, 'card' => $card];

        return $this;
    }
}
