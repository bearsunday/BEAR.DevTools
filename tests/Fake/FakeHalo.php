<?php

namespace BEAR\Dev;

use BEAR\Resource\ResourceObject;
class FakeHalo extends ResourceObject
{
    public $body = ['message' => 'Hello'];
    public function onGet(): static
    {
        return $this;
    }
}
