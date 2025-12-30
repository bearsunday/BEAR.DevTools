<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Resource\Page;

use BEAR\Resource\ResourceObject;

class Index extends ResourceObject
{
    public function onGet()
    {
        $this->body = ['method' => __FUNCTION__];

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function onPost(array $data = [])
    {
        $this->body = ['method' => __FUNCTION__, 'data' => $data];

        return $this;
    }

    public function onPut()
    {
        $this->body = ['method' => __FUNCTION__];

        return $this;
    }

    public function onPatch()
    {
        $this->body = ['method' => __FUNCTION__];

        return $this;
    }

    public function onDelete()
    {
        $this->body = ['method' => __FUNCTION__];

        return $this;
    }
}
