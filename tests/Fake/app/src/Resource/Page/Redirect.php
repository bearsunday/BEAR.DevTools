<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Resource\Page;

use BEAR\Resource\Code;
use BEAR\Resource\ResourceObject;

class Redirect extends ResourceObject
{
    public function onGet(): static
    {
        $this->code = Code::SEE_OTHER;
        $this->headers['Location'] = '/redirect';

        return $this;
    }

    public function onPost(): static
    {
        $this->code = Code::SEE_OTHER;
        $this->headers['Location'] = '/redirect';

        return $this;
    }

    public function onPut(): static
    {
        $this->code = Code::SEE_OTHER;
        $this->headers['Location'] = 'https://example.com/';

        return $this;
    }

    public function onDelete(): static
    {
        $this->code = Code::SEE_OTHER;

        return $this;
    }
}
