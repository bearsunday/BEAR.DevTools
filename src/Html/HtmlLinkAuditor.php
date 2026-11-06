<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use function is_string;
use function strtolower;

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

            $affordances[] = new HtmlAffordance(
                $href,
                $method,
                $attrs['rel'] ?? '',
                $attrs['class'] ?? '',
            );
        }

        return $affordances;
    }
}
