<?php

declare(strict_types=1);

namespace BEAR\Dev\LinkerApp\Resource\App;

use BEAR\Resource\ResourceObject;

class Post extends ResourceObject
{
    public function onGet(int $author_id): static
    {
        $this->body = [['id' => $author_id * 10, 'author_id' => $author_id]];

        return $this;
    }
}
