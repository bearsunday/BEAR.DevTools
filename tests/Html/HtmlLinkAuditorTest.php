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

    public function testEmptyTokenDoesNotMatchEmptyRelOrClass(): void
    {
        $affordance = new HtmlAffordance('/next', 'get');

        $this->assertFalse($affordance->hasToken(''));
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

    public function testSecondAnchorAmongMultipleIsFound(): void
    {
        $this->auditor->audit(
            [new LinkHeader('goNext', '/next')],
            '<a href="/prev" class="goPrev">Prev</a><a href="/next" class="goNext">Next</a>',
        );

        $this->assertSame([], $this->logger->warnings);
    }

    public function testNoAnchorsReportsTargetMissing(): void
    {
        $this->auditor->audit([new LinkHeader('goNext', '/next')], '<p>No links here</p>');

        $this->assertSame(['goNext:target-missing'], $this->logger->warnings);
    }

    public function testFormMethodOverrideMatchesDeleteWithValueUppercase(): void
    {
        $this->auditor->audit(
            [new LinkHeader('doDelete', '/checkout', 'delete')],
            '<form action="/checkout" method="post" class="doDelete">'
                . '<input type="hidden" name="_method" value="DELETE"></form>',
        );

        $this->assertSame([], $this->logger->warnings);
    }

    public function testFormMethodOverrideMatchesDeleteWithValueBeforeName(): void
    {
        $this->auditor->audit(
            [new LinkHeader('doDelete', '/checkout', 'delete')],
            '<form action="/checkout" method="post" class="doDelete">'
                . '<input type="hidden" value="delete" name="_method"></form>',
        );

        $this->assertSame([], $this->logger->warnings);
    }

    public function testFormMethodOverrideMismatchAgainstPut(): void
    {
        $this->auditor->audit(
            [new LinkHeader('doPut', '/checkout', 'put')],
            '<form action="/checkout" method="post" class="doPut">'
                . '<input type="hidden" name="_method" value="DELETE"></form>',
        );

        $this->assertSame(['doPut:method-mismatch'], $this->logger->warnings);
    }

    public function testFormWithoutMethodOverrideKeepsLiteralMethod(): void
    {
        $this->auditor->audit(
            [new LinkHeader('doDelete', '/checkout', 'delete')],
            '<form action="/checkout" method="post" class="doDelete"></form>',
        );

        $this->assertSame(['doDelete:method-mismatch'], $this->logger->warnings);
    }

    public function testAnchorWithQueryStringMatchesLinkByPath(): void
    {
        $this->auditor->audit(
            [new LinkHeader('viewProduct', '/admin/product')],
            '<a href="/admin/product?productCode=x" class="viewProduct">Product</a>',
        );

        $this->assertSame([], $this->logger->warnings);
    }

    public function testFormQueryStringMethodOverrideMatches(): void
    {
        $this->auditor->audit(
            [new LinkHeader('doBlock', '/admin/block/block', 'put')],
            '<form action="/admin/block/block?blockId=42&amp;_method=put" method="post" class="doBlock"></form>',
        );

        $this->assertSame([], $this->logger->warnings);
    }

    public function testFormQueryStringMethodOverrideMismatch(): void
    {
        $this->auditor->audit(
            [new LinkHeader('doBlock', '/admin/block/block', 'delete')],
            '<form action="/admin/block/block?blockId=42&amp;_method=put" method="post" class="doBlock"></form>',
        );

        $this->assertSame(['doBlock:method-mismatch'], $this->logger->warnings);
    }

    public function testHiddenInputOverrideTakesPrecedenceOverQueryStringOverride(): void
    {
        $this->auditor->audit(
            [new LinkHeader('doBlock', '/admin/block/block', 'put')],
            '<form action="/admin/block/block?blockId=42&amp;_method=delete" method="post" class="doBlock">'
                . '<input type="hidden" name="_method" value="put"></form>',
        );

        $this->assertSame([], $this->logger->warnings);
    }
}
