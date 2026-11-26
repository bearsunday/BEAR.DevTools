<?php

declare(strict_types=1);

namespace BEAR\Dev\Html;

use PHPUnit\Framework\TestCase;

use function array_column;

final class HtmlTagScannerTest extends TestCase
{
    public function testAttributesAreTokenized(): void
    {
        $tags = HtmlTagScanner::scan(
            '<a HREF="/next" class=\'goNext x\' rel=goNext title="a > b" data-href="/data" :href="vue" xlink:href="/xlink" hidden>N</a>',
            ['a'],
        );

        $this->assertSame([
            [
                'tag' => 'a',
                'attrs' => [
                    'href' => '/next',
                    'class' => 'goNext x',
                    'rel' => 'goNext',
                    'title' => 'a > b',
                    'data-href' => '/data',
                    ':href' => 'vue',
                    'xlink:href' => '/xlink',
                    'hidden' => '',
                ],
            ],
        ], $tags);
    }

    public function testNameInsideAnotherValueIsNotAttribute(): void
    {
        $tags = HtmlTagScanner::scan('<a title="href=/x class=goNext" href="/next">N</a>', ['a']);

        $this->assertSame(['title' => 'href=/x class=goNext', 'href' => '/next'], $tags[0]['attrs']);
    }

    public function testQuoteInsideUnquotedValueIsPartOfValue(): void
    {
        $tags = HtmlTagScanner::scan('<a href=foo"bar class=goNext>x</a><a href="/ok">', ['a']);

        $this->assertSame([
            ['tag' => 'a', 'attrs' => ['href' => 'foo"bar', 'class' => 'goNext']],
            ['tag' => 'a', 'attrs' => ['href' => '/ok']],
        ], $tags);
    }

    public function testFirstDuplicateWinsAndEntitiesAreDecoded(): void
    {
        $tags = HtmlTagScanner::scan('<a href="/a?x=1&amp;y=2" href="/b">', ['a']);

        $this->assertSame(['href' => '/a?x=1&y=2'], $tags[0]['attrs']);
    }

    public function testTagNamePrefixIsNotMatched(): void
    {
        $this->assertSame([], HtmlTagScanner::scan('<abbr href="/x"></abbr><a-b href="/y"></a-b>', ['a']));
        $this->assertSame([['tag' => 'a', 'attrs' => []]], HtmlTagScanner::scan('<a>', ['a']));
        $this->assertSame([['tag' => 'link', 'attrs' => ['href' => '/n']]], HtmlTagScanner::scan('<link href="/n"/>', ['link']));
    }

    public function testEndTagIsReturnedWhenAskedFor(): void
    {
        $tags = HtmlTagScanner::scan('<form action="/x"><input name="a"></form><input name="b"></div>', ['form', '/form', 'input']);

        $this->assertSame(['form', 'input', '/form', 'input'], array_column($tags, 'tag'));
    }

    public function testOnlyEndTagsCanBeAskedFor(): void
    {
        $this->assertSame([['tag' => '/form', 'attrs' => []]], HtmlTagScanner::scan('<form action="/x"></form>', ['/form']));
    }

    public function testEndTagWithAttributesIsAnEndTag(): void
    {
        $tags = HtmlTagScanner::scan('<form action="/x"></form x><input name="b">', ['form', '/form', 'input']);

        $this->assertSame(['form', '/form', 'input'], array_column($tags, 'tag'));
    }
}
