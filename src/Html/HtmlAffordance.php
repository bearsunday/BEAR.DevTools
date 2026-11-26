<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use function array_map;
use function count;
use function explode;
use function http_build_query;
use function implode;
use function in_array;
use function is_array;
use function parse_str;
use function parse_url;
use function preg_split;
use function rawurlencode;
use function strtolower;
use function trim;

use const PHP_URL_QUERY;

final readonly class HtmlAffordance
{
    /** The field that would turn a POST form into another method; an HTML form submits GET or POST only */
    public const METHOD_OVERRIDE = '_method';

    /** The form index of an input outside any form, whose value no affordance sends */
    private const OUTSIDE_FORM = -1;

    /** @param array<string, mixed> $hidden The hidden inputs of a form, which a browser sends with it, nested as PHP parses `tags[]` or `user[name]` */
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
        /** @var array<int, list<array{string, string}>> $hidden */
        $hidden = [];
        $form = self::OUTSIDE_FORM;
        foreach (HtmlTagScanner::scan($html, ['a', 'area', 'link', 'form', '/form', 'input']) as ['tag' => $tag, 'attrs' => $attrs]) {
            if ($tag === 'input') {
                $input = self::hiddenInput($attrs);
                if ($input !== null) {
                    $hidden[$form][] = $input;
                }

                continue;
            }

            $affordance = self::affordance($tag, $attrs);
            if ($tag === 'form' || $tag === '/form') {
                $form = $affordance === null ? self::OUTSIDE_FORM : count($found);
            }

            if ($affordance === null) {
                continue;
            }

            $found[] = $affordance;
        }

        $affordances = [];
        foreach ($found as $i => [$href, $method, $rel, $class]) {
            $affordances[] = new self($href, $method, $rel, $class, self::formFields($hidden[$i] ?? []));
        }

        return $affordances;
    }

    /**
     * The fields the inputs submit, in the structure PHP gives a submitted form
     *
     * @param list<array{string, string}> $inputs
     *
     * @return array<string, mixed>
     */
    private static function formFields(array $inputs): array
    {
        $pairs = array_map(static fn (array $input): string => rawurlencode($input[0]) . '=' . rawurlencode($input[1]), $inputs);
        parse_str(implode('&', $pairs), $fields);
        $named = [];
        foreach ($fields as $name => $value) {
            $named[(string) $name] = $value;
        }

        return $named;
    }

    /** The href without the method override in its query: the target a declared link names */
    public function target(): string
    {
        parse_str((string) parse_url($this->href, PHP_URL_QUERY), $query);
        if (! isset($query[self::METHOD_OVERRIDE])) {
            return $this->href;
        }

        unset($query[self::METHOD_OVERRIDE]);
        [$path] = explode('?', $this->href, 2);

        return $query === [] ? $path : $path . '?' . http_build_query($query);
    }

    public function hasToken(string $token): bool
    {
        return $this->containsToken($this->rel, $token) || $this->containsToken($this->class, $token);
    }

    /** Whether a POST form carries the method override, as a hidden input or in the query of its action */
    public function overridesMethod(): bool
    {
        parse_str((string) parse_url($this->href, PHP_URL_QUERY), $query);

        return $this->method === 'post' && (isset($this->hidden[self::METHOD_OVERRIDE]) || isset($query[self::METHOD_OVERRIDE]));
    }

    /**
     * The href, method, rel and class of a link or a form that has an href or action
     *
     * @param array<string, string> $attrs
     *
     * @return array{string, string, string, string}|null
     */
    private static function affordance(string $tag, array $attrs): array|null
    {
        $href = $tag === 'form' ? $attrs['action'] ?? null : $attrs['href'] ?? null;
        if ($href === null || $href === '') {
            return null;
        }

        // A form submits GET or POST: a missing or any other method attribute is GET, as a browser reads it.
        $method = $tag === 'form' && strtolower($attrs['method'] ?? '') === 'post' ? 'post' : 'get';

        return [$href, $method, $attrs['rel'] ?? '', $attrs['class'] ?? ''];
    }

    /**
     * The name and value of a hidden input a browser submits; a disabled one is not submitted
     *
     * @param array<string, string> $attrs
     *
     * @return array{string, string}|null
     */
    private static function hiddenInput(array $attrs): array|null
    {
        $name = $attrs['name'] ?? '';
        if ($name === '' || strtolower($attrs['type'] ?? '') !== 'hidden' || isset($attrs['disabled'])) {
            return null;
        }

        return [$name, $attrs['value'] ?? ''];
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
