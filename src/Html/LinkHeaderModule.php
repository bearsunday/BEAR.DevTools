<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use BEAR\Resource\RenderInterface;
use Override;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

final class LinkHeaderModule extends AbstractModule
{
    public function __construct(AbstractModule $module)
    {
        parent::__construct($module);
    }

    #[Override]
    protected function configure(): void
    {
        $this->rename(RenderInterface::class, 'html');
        $this->bind(RenderInterface::class)->to(LinkHeaderRenderer::class)->in(Scope::SINGLETON);
        $this->bind(HtmlLinkAuditor::class);
        $this->bind(HtmlLinkAuditLoggerInterface::class)->to(ErrorLogHtmlLinkAuditLogger::class);
    }
}
