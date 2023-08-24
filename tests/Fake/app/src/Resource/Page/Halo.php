<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Resource\Page;

use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\ResourceObject;

class Halo extends ResourceObject
{
    public $body = [
        'greeting' => 'Hello World!'
    ];

    #[Embed(src: 'app://self/ja?id=1', rel: 'ja')]
    public function onGet(): static
    {
        return $this;
    }
}
