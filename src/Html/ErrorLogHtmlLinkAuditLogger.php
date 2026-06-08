<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use Override;

use function error_log;
use function sprintf;

final class ErrorLogHtmlLinkAuditLogger implements HtmlLinkAuditLoggerInterface
{
    #[Override]
    public function warning(LinkHeader $link, string $reason): void
    {
        error_log(sprintf(
            '[BEAR.Dev.HtmlLinkAudit] warning rel=%s method=%s href=%s reason=%s',
            $link->rel,
            $link->method,
            $link->href,
            $reason,
        ));
    }
}
