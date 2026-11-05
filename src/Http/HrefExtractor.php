<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\Html\HtmlTagScanner;
use BEAR\Resource\ResourceObject;
use stdClass;

use function html_entity_decode;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function preg_quote;
use function preg_split;
use function strtolower;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;

final class HrefExtractor
{
    public function href(string $rel, ResourceObject $ro): string|null
    {
        return $this->halHref($rel, $ro)
            ?? $this->semanticHtmlHref($rel, $ro->view)
            ?? $this->linkHeaderHref($rel, $ro->headers);
    }

    private function halHref(string $rel, ResourceObject $ro): string|null
    {
        $body = is_array($ro->body) ? $ro->body : [];
        $links = $body['_links'] ?? null;
        if ($links instanceof stdClass) {
            $links = (array) $links;
        }

        if (! is_array($links) || ! isset($links[$rel])) {
            return null;
        }

        $link = $links[$rel];
        if ($link instanceof stdClass) {
            $link = (array) $link;
        }

        if (! is_array($link)) {
            return null;
        }

        $href = $link['href'] ?? null;

        return is_string($href) ? $href : null;
    }

    private function semanticHtmlHref(string $rel, string|null $view): string|null
    {
        if (! is_string($view) || $view === '') {
            return null;
        }

        foreach (HtmlTagScanner::scan($view, ['a', 'area', 'link']) as ['attrs' => $attrs]) {
            if (! $this->hasLinkToken($attrs, $rel)) {
                continue;
            }

            $href = $attrs['href'] ?? null;
            if ($href !== null && $href !== '') {
                return $href;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $headers */
    private function linkHeaderHref(string $rel, array $headers): string|null
    {
        $header = $this->headerValue($headers, 'Link');
        if ($header === null) {
            return null;
        }

        $links = preg_split('/,\s*(?=<)/', $header);
        if (! is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (preg_match('/^\s*<([^>]*)>\s*(.*)$/', $link, $match) !== 1) {
                continue;
            }

            $linkRel = $this->linkHeaderParam($match[2], 'rel');
            if ($linkRel === null || ! $this->containsToken($linkRel, $rel)) {
                continue;
            }

            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    /** @param array<string, string> $attrs */
    private function hasLinkToken(array $attrs, string $rel): bool
    {
        return $this->containsToken($attrs['rel'] ?? '', $rel)
            || $this->containsToken($attrs['class'] ?? '', $rel);
    }

    private function linkHeaderParam(string $attrs, string $name): string|null
    {
        if (preg_match('/(?:^|;)\s*' . preg_quote($name, '/') . '\s*=\s*(?:"([^"]*)"|([^;\s]+))/i', $attrs, $match) !== 1) {
            return null;
        }

        if ($match[1] !== '') {
            return $match[1];
        }

        return $match[2] ?? null;
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

    /** @param array<string, mixed> $headers */
    private function headerValue(array $headers, string $name): string|null
    {
        foreach ($headers as $header => $value) {
            if (! is_string($value)) {
                continue;
            }

            if (strtolower($header) === strtolower($name)) {
                return $value;
            }
        }

        return null;
    }
}
