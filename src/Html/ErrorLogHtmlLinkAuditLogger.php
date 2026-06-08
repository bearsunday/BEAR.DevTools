<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use Override;

use function error_log;
use function is_string;
use function preg_replace;
use function sprintf;
use function strlen;
use function substr;

final class ErrorLogHtmlLinkAuditLogger implements HtmlLinkAuditLoggerInterface
{
    #[Override]
    public function warning(LinkHeader $link, string $reason): void
    {
        error_log(sprintf(
            '[BEAR.Dev.HtmlLinkAudit] warning rel=%s method=%s href=%s reason=%s',
            $this->sanitize($link->rel),
            $this->sanitize($link->method),
            $this->sanitize($link->href),
            $this->sanitize($reason),
        ));
    }

    private function sanitize(string $value): string
    {
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
        if (! is_string($sanitized)) {
            return '';
        }

        return strlen($sanitized) > 512 ? substr($sanitized, 0, 512) . '...' : $sanitized;
    }
}
