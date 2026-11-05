<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use function implode;
use function preg_match_all;
use function preg_quote;
use function strtolower;

use const PREG_SET_ORDER;

/**
 * Finds start tags in rendered HTML, skipping HTML comments.
 *
 * Comments and tags are matched in a single left-to-right pass, so a `<!--`
 * inside a quoted attribute value (e.g. `<div title="<!--">`) is consumed as
 * part of its tag and never starts a comment. Comment terminators follow the
 * HTML tokenizer: `<!-->` and `<!--->` close immediately, `--!>` also ends a
 * comment, and an unclosed comment runs to the end of the document.
 *
 * @internal
 */
final class HtmlTagScanner
{
    private const COMMENT = '<!---?>|<!--.*?(?:--!?>|\z)';
    private const ATTRS = '(?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+';

    /**
     * @param non-empty-list<string> $tags Tag names to return
     *
     * @return list<array{tag: string, attrs: string}>
     */
    public static function scan(string $html, array $tags): array
    {
        $names = [];
        foreach ($tags as $tag) {
            $names[] = preg_quote($tag, '/');
        }

        $pattern = '/' . self::COMMENT
            . '|<(?P<tag>' . implode('|', $names) . ')\b(?P<attrs>' . self::ATTRS . ')>'
            . '|<[a-z][^\s\/>]*' . self::ATTRS . '>/is';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) === false) {
            return []; // @codeCoverageIgnore
        }

        $found = [];
        foreach ($matches as $match) {
            if (! isset($match['tag']) || $match['tag'] === '') {
                continue; // a comment or a tag that was not asked for
            }

            $found[] = ['tag' => strtolower($match['tag']), 'attrs' => $match['attrs']];
        }

        return $found;
    }
}
