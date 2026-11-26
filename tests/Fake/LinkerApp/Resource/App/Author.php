<?php

declare(strict_types=1);

namespace BEAR\Dev\LinkerApp\Resource\App;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

class Author extends ResourceObject
{
    #[Link(crawl: 'tree', rel: 'posts', href: 'app://self/post?author_id={id}')]
    public function onGet(int $id): static
    {
        $this->body = ['id' => $id];

        return $this;
    }
}
