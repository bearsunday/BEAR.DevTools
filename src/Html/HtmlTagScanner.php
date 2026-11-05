<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use function html_entity_decode;
use function implode;
use function preg_match_all;
use function preg_quote;
use function strtolower;

use const ENT_HTML5;
use const ENT_QUOTES;
use const PREG_SET_ORDER;
use const PREG_UNMATCHED_AS_NULL;

/**
 * Finds start tags in rendered HTML, skipping HTML comments, and tokenizes their attributes.
 *
 * Comments and tags are matched in a single left-to-right pass, so a `<!--`
 * inside a quoted attribute value (e.g. `<div title="<!--">`) is consumed as
 * part of its tag and never starts a comment. Comment terminators follow the
 * HTML tokenizer: `<!-->` and `<!--->` close immediately, `--!>` also ends a
 * comment, and an unclosed comment runs to the end of the document.
 *
 * Attributes are tokenized as the HTML tokenizer does: a name runs up to
 * whitespace, `/`, `>` or `=`, and an unquoted value runs up to whitespace or
 * `>` (a quote inside it is part of the value). Text inside another attribute's
 * value is therefore never read as an attribute, and names such as `data-href`,
 * `:href` or `xlink:href` are distinct from `href`.
 *
 * @internal
 */
final class HtmlTagScanner
{
    private const COMMENT = '<!---?>|<!--.*?(?:--!?>|\z)';
    private const ATTR_NAME = '[^\s\/>][^\s\/>=]*+';
    private const ATTRS = '(?:\s++|\/|' . self::ATTR_NAME . '(?:\s*+=\s*+(?:"[^"]*+"|\'[^\']*+\'|[^\s>]*+))?)*+';
    private const ATTR = '/(?P<name>' . self::ATTR_NAME . ')(?:\s*+=\s*+(?:"(?P<dq>[^"]*+)"|\'(?P<sq>[^\']*+)\'|(?P<uq>[^\s>]*+)))?/';

    /**
     * @param non-empty-list<string> $tags Tag names to return
     *
     * @return list<array{tag: string, attrs: array<string, string>}>
     */
    public static function scan(string $html, array $tags): array
    {
        $names = [];
        foreach ($tags as $tag) {
            $names[] = preg_quote($tag, '/');
        }

        $pattern = '/' . self::COMMENT
            . '|<(?P<tag>' . implode('|', $names) . ')(?=[\s\/>])(?P<attrs>' . self::ATTRS . ')>'
            . '|<[a-z][^\s\/>]*+' . self::ATTRS . '>/is';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) === false) {
            return []; // @codeCoverageIgnore
        }

        $found = [];
        foreach ($matches as $match) {
            if (! isset($match['tag']) || $match['tag'] === '') {
                continue; // a comment or a tag that was not asked for
            }

            $found[] = ['tag' => strtolower($match['tag']), 'attrs' => self::attributes($match['attrs'])];
        }

        return $found;
    }

    /**
     * Returns attribute values keyed by lower-cased name. As in HTML, the first
     * occurrence of a duplicated name wins and a name without a value is ''.
     *
     * @return array<string, string>
     */
    private static function attributes(string $attrs): array
    {
        if (preg_match_all(self::ATTR, $attrs, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL) === false) {
            return []; // @codeCoverageIgnore
        }

        $attributes = [];
        foreach ($matches as $match) {
            $name = strtolower((string) $match['name']);
            if (isset($attributes[$name])) {
                continue;
            }

            $value = $match['dq'] ?? $match['sq'] ?? $match['uq'] ?? '';
            $attributes[$name] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
        }

        return $attributes;
    }
}
