<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use function count;
use function in_array;
use function is_array;
use function parse_str;
use function parse_url;
use function preg_split;
use function strtolower;
use function trim;

use const PHP_URL_QUERY;

final readonly class HtmlAffordance
{
    /** The field that would turn a POST form into another method; an HTML form submits GET or POST only */
    public const METHOD_OVERRIDE = '_method';

    /** @param array<string, string> $hidden The hidden inputs of a form, which a browser sends with it */
    public function __construct(
        public string $href,
        public string $method,
        public string $rel = '',
        public string $class = '',
        public array $hidden = [],
    ) {
    }

    /**
     * The `<a>`, `<area>`, `<link>` and `<form>` of the HTML that have an href or action, in document order
     *
     * @return list<self>
     */
    public static function listFromHtml(string $html): array
    {
        /** @var list<array{string, string, string, string}> $found */
        $found = [];
        /** @var array<int, array<string, string>> $hidden */
        $hidden = [];
        $form = null;
        foreach (HtmlTagScanner::scan($html, ['a', 'area', 'link', 'form', '/form', 'input']) as ['tag' => $tag, 'attrs' => $attrs]) {
            if ($tag === 'input') {
                $name = $attrs['name'] ?? '';
                if ($form !== null && $name !== '' && strtolower($attrs['type'] ?? '') === 'hidden') {
                    $hidden[$form][$name] = $attrs['value'] ?? '';
                }

                continue;
            }

            if ($tag === 'form' || $tag === '/form') {
                $form = null;
            }

            $href = $tag === 'form' ? $attrs['action'] ?? null : $attrs['href'] ?? null;
            if ($href === null || $href === '') {
                continue;
            }

            // A form submits GET or POST: a missing or any other method attribute is GET, as a browser reads it.
            $method = $tag === 'form' && strtolower($attrs['method'] ?? '') === 'post' ? 'post' : 'get';

            $found[] = [$href, $method, $attrs['rel'] ?? '', $attrs['class'] ?? ''];
            if ($tag !== 'form') {
                continue;
            }

            $form = count($found) - 1;
        }

        $affordances = [];
        foreach ($found as $i => [$href, $method, $rel, $class]) {
            $affordances[] = new self($href, $method, $rel, $class, $hidden[$i] ?? []);
        }

        return $affordances;
    }

    public function hasToken(string $token): bool
    {
        return $this->containsToken($this->rel, $token) || $this->containsToken($this->class, $token);
    }

    /** Whether the form carries the method override, as a hidden input or in the query of its action */
    public function overridesMethod(): bool
    {
        parse_str((string) parse_url($this->href, PHP_URL_QUERY), $query);

        return isset($this->hidden[self::METHOD_OVERRIDE]) || isset($query[self::METHOD_OVERRIDE]);
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
