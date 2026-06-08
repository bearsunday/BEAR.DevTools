<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use function in_array;
use function is_array;
use function preg_split;
use function trim;

final readonly class HtmlAffordance
{
    public function __construct(
        public string $href,
        public string $method,
        public string $rel = '',
        public string $class = '',
    ) {
    }

    public function hasToken(string $token): bool
    {
        return $this->containsToken($this->rel, $token) || $this->containsToken($this->class, $token);
    }

    private function containsToken(string $value, string $token): bool
    {
        $value = trim($value);
        if ($value === '' || $token === '') {
            return false;
        }

        $tokens = preg_split('/\s+/', $value);
        if (! is_array($tokens)) {
            return false;
        }

        return in_array($token, $tokens, true);
    }
}
