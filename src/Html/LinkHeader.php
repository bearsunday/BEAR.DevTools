<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use function addcslashes;
use function strtolower;

final readonly class LinkHeader
{
    public function __construct(
        public string $rel,
        public string $href,
        public string $method = 'get',
        public string $title = '',
    ) {
    }

    public function headerValue(): string
    {
        $header = '<' . $this->href . '>; rel="' . $this->quote($this->rel) . '"';
        $method = strtolower($this->method);
        if ($method !== '') {
            $header .= '; method="' . $this->quote($method) . '"';
        }

        if ($this->title !== '') {
            $header .= '; title="' . $this->quote($this->title) . '"';
        }

        return $header;
    }

    private function quote(string $value): string
    {
        return addcslashes($value, '\"');
    }
}
