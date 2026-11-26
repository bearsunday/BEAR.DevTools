<?php

declare(strict_types=1);

namespace BEAR\Dev\LinkerApp\Resource\App;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

class Scalar extends ResourceObject
{
    #[Link(crawl: 'tree', rel: 'posts', href: 'app://self/post?author_id=1')]
    public function onGet(): static
    {
        $this->body = 'bear';

        return $this;
    }
}
