<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use function in_array;
use function is_array;
use function preg_split;
use function strtolower;
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

    /**
     * The `<a>`, `<area>`, `<link>` and `<form>` of the HTML that have an href or action, in document order
     *
     * @return list<self>
     */
    public static function listFromHtml(string $html): array
    {
        $affordances = [];
        foreach (HtmlTagScanner::scan($html, ['a', 'area', 'link', 'form']) as ['tag' => $tag, 'attrs' => $attrs]) {
            $href = $tag === 'form' ? $attrs['action'] ?? null : $attrs['href'] ?? null;
            if ($href === null || $href === '') {
                continue;
            }

            // A form's missing or empty method attribute means GET.
            $method = $tag === 'form' ? strtolower($attrs['method'] ?? '') : '';
            if ($method === '') {
                $method = 'get';
            }

            $affordances[] = new self(
                $href,
                $method,
                $attrs['rel'] ?? '',
                $attrs['class'] ?? '',
            );
        }

        return $affordances;
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
