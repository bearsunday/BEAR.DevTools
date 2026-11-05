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

    public function testMultipleAffordances(): void
    {
        $this->auditor->audit(
            [new LinkHeader('goNext', '/next')],
            '<a href="/home">Home</a><a href="/next" class="goNext">Next</a><a href="/about">About</a>',
        );

        $this->assertSame([], $this->logger->warnings);
    }

    public function testSingleQuotedAndUnquotedAttributes(): void
    {
        $this->auditor->audit(
            [new LinkHeader('goNext', '/next'), new LinkHeader('goPrev', '/prev')],
            "<a href='/next' class='goNext'>Next</a><a href=/prev rel=goPrev>Prev</a>",
        );

        $this->assertSame([], $this->logger->warnings);
    }

    public function testDataAttributeIsNotTarget(): void
    {
        $this->auditor->audit(
            [new LinkHeader('goNext', '/next')],
            '<a data-href="/next" href="/other" class="goNext">Next</a>',
        );

        $this->assertSame(['goNext:target-missing'], $this->logger->warnings);
    }

    public function testRealAttributeIsReadNextToDataAttribute(): void
    {
        $this->auditor->audit(
            [new LinkHeader('goNext', '/other')],
            '<a data-href="/next" href="/other" class="goNext">Next</a>',
        );

        $this->assertSame([], $this->logger->warnings);
    }

    public function testCommentedOutLinkIsNotTarget(): void
    {
        $this->auditor->audit(
            [new LinkHeader('goNext', '/next')],
            '<!-- <a href="/next" class="goNext">Next</a> --><a href="/other">Other</a>',
        );

        $this->assertSame(['goNext:target-missing'], $this->logger->warnings);
    }

    /** @return array<string, array{string}> */
    public static function commentEdgeCaseProvider(): array
    {
        return [
            'abrupt empty comment' => ['<!--><a href="/next" class="goNext">Next</a><!-- c -->'],
            'abrupt dash comment' => ['<!---><a href="/next" class="goNext">Next</a><!-- c -->'],
            'bang-closed comment' => ['<!-- x --!><a href="/next" class="goNext">Next</a><!-- c -->'],
        ];
    }

    /** @dataProvider commentEdgeCaseProvider */
    public function testLinkAfterEarlyClosedCommentIsTarget(string $html): void
    {
        $this->auditor->audit([new LinkHeader('goNext', '/next')], $html);

        $this->assertSame([], $this->logger->warnings);
    }

    public function testLinkInsideUnclosedCommentIsNotTarget(): void
    {
        $this->auditor->audit(
            [new LinkHeader('goNext', '/next')],
            '<p>x</p><!-- <a href="/next" class="goNext">Next</a>',
        );

        $this->assertSame(['goNext:target-missing'], $this->logger->warnings);
    }

    public function testCommentOpenerInsideAttributeValueIsNotComment(): void
    {
        $this->auditor->audit(
            [new LinkHeader('goNext', '/next')],
            '<div title="<!--"><a href="/next" class="goNext">Next</a></div>',
        );

        $this->assertSame([], $this->logger->warnings);
    }

    public function testGreaterThanInsideAttributeValue(): void
    {
        $this->auditor->audit(
            [new LinkHeader('goNext', '/next')],
            '<a class="goNext" title="a>b" href="/next">Next</a>',
        );

        $this->assertSame([], $this->logger->warnings);
    }

    public function testNameInsideAnotherAttributeValueIsNotTarget(): void
    {
        $this->auditor->audit(
            [new LinkHeader('goNext', '/next')],
            '<a title="see href=/next here" href="/other" class="goNext">Next</a>',
        );

        $this->assertSame(['goNext:target-missing'], $this->logger->warnings);
    }

    public function testFormWithSingleQuotedUppercaseMethod(): void
    {
        $this->auditor->audit(
            [new LinkHeader('doCheckout', '/checkout', 'post')],
            "<form action='/checkout' method='POST' class='doCheckout'></form>",
        );

        $this->assertSame([], $this->logger->warnings);
    }

    public function testFormWithValuelessMethodIsGet(): void
    {
        $this->auditor->audit(
            [new LinkHeader('goNext', '/next')],
            '<form action="/next" method class="goNext"></form>',
        );

        $this->assertSame([], $this->logger->warnings);
    }
}
