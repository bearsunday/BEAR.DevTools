<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

interface HtmlLinkAuditLoggerInterface
{
    public function warning(LinkHeader $link, string $reason): void;
}
