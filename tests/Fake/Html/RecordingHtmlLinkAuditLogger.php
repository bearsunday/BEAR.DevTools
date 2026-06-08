<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use Override;

final class RecordingHtmlLinkAuditLogger implements HtmlLinkAuditLoggerInterface
{
    /** @var list<string> */
    public array $warnings = [];

    #[Override]
    public function warning(LinkHeader $link, string $reason): void
    {
        $this->warnings[] = $link->rel . ':' . $reason;
    }
}
