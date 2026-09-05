<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use function html_entity_decode;
use function is_string;
use function preg_match;
use function preg_match_all;
use function strtolower;

use const ENT_HTML5;
use const ENT_QUOTES;
use const PREG_SET_ORDER;

final readonly class HtmlLinkAuditor
{
    public function __construct(
        private HtmlLinkAuditLoggerInterface $logger,
    ) {
    }

    /** @param list<LinkHeader> $links */
    public function audit(array $links, string|null $html): void
    {
        if (! is_string($html) || $html === '') {
            foreach ($links as $link) {
                $this->logger->warning($link, 'html-missing');
            }

            return;
        }

        $affordances = $this->affordances($html);
        foreach ($links as $link) {
            $this->auditLink($link, $affordances);
        }
    }

    /** @param list<HtmlAffordance> $affordances */
    private function auditLink(LinkHeader $link, array $affordances): void
    {
        $targetMatches = [];
        foreach ($affordances as $affordance) {
            if ($affordance->href !== $link->href) {
                continue;
            }

            $targetMatches[] = $affordance;
        }

        if ($targetMatches === []) {
            $this->logger->warning($link, 'target-missing');

            return;
        }

        $methodMatches = [];
        $method = strtolower($link->method);
        foreach ($targetMatches as $affordance) {
            if ($affordance->method !== $method) {
                continue;
            }

            $methodMatches[] = $affordance;
        }

        if ($methodMatches === []) {
            $this->logger->warning($link, 'method-mismatch');

            return;
        }

        foreach ($methodMatches as $affordance) {
            if ($affordance->hasToken($link->rel)) {
                return;
            }
        }

        $this->logger->warning($link, 'semantic-token-missing');
    }

    /** @return list<HtmlAffordance> */
    private function affordances(string $html): array
    {
        $affordances = [];
        if (preg_match_all('/<form\b(?P<attrs>[^>]*)>(?P<body>.*?)<\/form>/is', $html, $formMatches, PREG_SET_ORDER) === false) {
            return $affordances;
        }

        foreach ($formMatches as $match) {
            $affordance = $this->formAffordance($match['attrs'], $match['body']);
            if ($affordance === null) {
                continue;
            }

            $affordances[] = $affordance;
        }

        if (preg_match_all('/<(?:a|area|link)\b(?P<attrs>[^>]*)>/i', $html, $tagMatches, PREG_SET_ORDER) === false) {
            return $affordances;
        }

        foreach ($tagMatches as $match) {
            $href = $this->attribute($match['attrs'], 'href');
            if ($href === null || $href === '') {
                continue;
            }

            $affordances[] = new HtmlAffordance(
                $href,
                'get',
                $this->attribute($match['attrs'], 'rel') ?? '',
                $this->attribute($match['attrs'], 'class') ?? '',
            );
        }

        return $affordances;
    }

    private function formAffordance(string $attrs, string $body): HtmlAffordance|null
    {
        $href = $this->attribute($attrs, 'action');
        if ($href === null || $href === '') {
            return null;
        }

        $method = $this->methodOverride($body) ?? strtolower($this->attribute($attrs, 'method') ?? 'get');

        return new HtmlAffordance(
            $href,
            $method,
            $this->attribute($attrs, 'rel') ?? '',
            $this->attribute($attrs, 'class') ?? '',
        );
    }

    private function methodOverride(string $body): string|null
    {
        if (preg_match_all('/<input\b(?P<attrs>[^>]*)>/is', $body, $matches, PREG_SET_ORDER) === false) {
            return null;
        }

        foreach ($matches as $match) {
            if ($this->attribute($match['attrs'], 'name') !== '_method') {
                continue;
            }

            $value = $this->attribute($match['attrs'], 'value');

            return $value === null ? null : strtolower($value);
        }

        return null;
    }

    private function attribute(string $attrs, string $name): string|null
    {
        if (preg_match('/\b' . $name . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $match) !== 1) {
            return null;
        }

        $value = $match[1] ?? $match[2] ?? $match[3] ?? '';

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
    }
}
