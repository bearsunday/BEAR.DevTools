<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Resource\NullResourceObject;
use PHPUnit\Framework\TestCase;

final class HrefExtractorTest extends TestCase
{
    public function testSemanticHtmlHref(): void
    {
        $this->assertSame('/next', (new HrefExtractor())->href('goNext', $this->ro('<a href="/next" class="goNext">Next</a>')));
    }

    public function testDataAttributeIsNotHref(): void
    {
        $ro = $this->ro('<a data-href="/wrong" href="/next" class="goNext">Next</a>');

        $this->assertSame('/next', (new HrefExtractor())->href('goNext', $ro));
    }

    public function testCommentedOutLinkIsIgnored(): void
    {
        $ro = $this->ro('<!-- <a href="/old" class="goNext">Old</a> --><a href="/next" class="goNext">Next</a>');

        $this->assertSame('/next', (new HrefExtractor())->href('goNext', $ro));
    }

    public function testLinkAfterEarlyClosedCommentIsFound(): void
    {
        $ro = $this->ro('<!--><a href="/next" class="goNext">N</a><!-- --><a href="/old" class="goNext">Old</a>');

        $this->assertSame('/next', (new HrefExtractor())->href('goNext', $ro));
    }

    public function testLinkInsideUnclosedCommentIsIgnored(): void
    {
        $ro = $this->ro('<a href="/next" class="goNext">Next</a><!-- <a href="/old" class="goNext">Old</a>');
        $this->assertSame('/next', (new HrefExtractor())->href('goNext', $ro));

        $this->assertNull((new HrefExtractor())->href('goNext', $this->ro('<!-- <a href="/old" class="goNext">Old</a>')));
    }

    public function testSingleQuotedRelAttribute(): void
    {
        $this->assertSame('/next', (new HrefExtractor())->href('goNext', $this->ro("<link rel='goNext' href='/next'>")));
    }

    public function testCommentOpenerInsideAttributeValueIsNotComment(): void
    {
        $ro = $this->ro('<div title="<!--"><a href="/next" class="goNext">Next</a></div>');

        $this->assertSame('/next', (new HrefExtractor())->href('goNext', $ro));
    }

    public function testNameInsideAnotherAttributeValueIsIgnored(): void
    {
        $ro = $this->ro('<a title="href=/x" href="/next" class="goNext">Next</a>');

        $this->assertSame('/next', (new HrefExtractor())->href('goNext', $ro));
    }

    public function testPrefixedHrefIsNotHref(): void
    {
        $ro = $this->ro('<a :href="url" xlink:href="/x" href="/next" class="goNext">Next</a>');

        $this->assertSame('/next', (new HrefExtractor())->href('goNext', $ro));
    }

    public function testPrefixedHrefAloneIsNotHref(): void
    {
        $this->assertNull((new HrefExtractor())->href('goNext', $this->ro('<a xlink:href="/wrong" class="goNext">Next</a>')));
    }

    public function testQuoteInsideUnquotedValue(): void
    {
        $this->assertSame('/next"x', (new HrefExtractor())->href('goNext', $this->ro('<a href=/next"x class=goNext>N</a>')));
    }

    private function ro(string $view): NullResourceObject
    {
        $ro = new NullResourceObject();
        $ro->body = [];
        $ro->view = $view;

        return $ro;
    }
}
