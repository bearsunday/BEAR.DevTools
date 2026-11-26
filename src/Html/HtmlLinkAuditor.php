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

        $affordances = HtmlAffordance::listFromHtml($html);
        foreach ($links as $link) {
            $this->auditLink($link, $affordances);
        }
    }

    /** @param list<HtmlAffordance> $affordances */
    private function auditLink(LinkHeader $link, array $affordances): void
    {
        $targetMatches = [];
        foreach ($affordances as $affordance) {
            if ($affordance->target() !== $link->href) {
                continue;
            }

            $targetMatches[] = $affordance;
        }

        if ($targetMatches === []) {
            $this->logger->warning($link, 'target-missing');

            return;
        }

        $methodMatches = [];
        // HTML has no PUT or DELETE: a link that is not a GET is a POST form
        $method = strtolower($link->method) === 'get' ? 'get' : 'post';
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

        $this->auditToken($link, $methodMatches);
    }

    /** @param non-empty-list<HtmlAffordance> $methodMatches */
    private function auditToken(LinkHeader $link, array $methodMatches): void
    {
        foreach ($methodMatches as $affordance) {
            if (! $affordance->hasToken($link->rel)) {
                continue;
            }

            if ($affordance->overridesMethod()) {
                $this->logger->warning($link, 'method-override');
            }

            return;
        }

        $this->logger->warning($link, 'semantic-token-missing');
    }
}
