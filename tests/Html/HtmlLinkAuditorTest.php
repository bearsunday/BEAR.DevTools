<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use PHPUnit\Framework\TestCase;

final class HtmlLinkAuditorTest extends TestCase
{
    private RecordingHtmlLinkAuditLogger $logger;
    private HtmlLinkAuditor $auditor;

    protected function setUp(): void
    {
        $this->logger = new RecordingHtmlLinkAuditLogger();
        $this->auditor = new HtmlLinkAuditor($this->logger);
    }

    public function testMissingTarget(): void
    {
        $this->auditor->audit([new LinkHeader('goNext', '/next')], '<a href="/other">Other</a>');

        $this->assertSame(['goNext:target-missing'], $this->logger->warnings);
    }

    public function testMissingSemanticToken(): void
    {
        $this->auditor->audit([new LinkHeader('goNext', '/next')], '<a href="/next">Next</a>');

        $this->assertSame(['goNext:semantic-token-missing'], $this->logger->warnings);
    }

    public function testSemanticAnchor(): void
    {
        $this->auditor->audit([new LinkHeader('goNext', '/next')], '<a href="/next" class="goNext">Next</a>');

        $this->assertSame([], $this->logger->warnings);
    }

    public function testUnsafeMethodForm(): void
    {
        $this->auditor->audit(
            [new LinkHeader('doCheckout', '/checkout', 'post')],
            '<form action="/checkout" method="post" class="doCheckout"></form>',
        );

        $this->assertSame([], $this->logger->warnings);
    }

    public function testUnsafeMethodMismatch(): void
    {
        $this->auditor->audit(
            [new LinkHeader('doCheckout', '/checkout', 'post')],
            '<a href="/checkout" class="doCheckout">Checkout</a>',
        );

        $this->assertSame(['doCheckout:method-mismatch'], $this->logger->warnings);
    }
}
