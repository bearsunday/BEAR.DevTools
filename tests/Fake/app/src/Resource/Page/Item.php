<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Resource\Page;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\Code;
use BEAR\Resource\ResourceObject;

class Item extends ResourceObject
{
    #[Link(rel: 'goForm', href: '/form')]
    public function onGet(int $id): static
    {
        $this->body = ['id' => $id];

        return $this;
    }

    public function onPost(string $name): static
    {
        $this->code = Code::CREATED;
        $this->headers['Location'] = '/item?id=5';
        $this->body = ['id' => 5, 'name' => $name];

        return $this;
    }

    public function onPut(int $id): static
    {
        $this->code = Code::SEE_OTHER;
        $this->headers['Location'] = "/item?id={$id}";

        return $this;
    }
}
