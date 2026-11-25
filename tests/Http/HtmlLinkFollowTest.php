<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use PHPUnit\Framework\TestCase;

use function dirname;

final class HtmlLinkFollowTest extends TestCase
{
    /** doCreate is a form whose hidden input carries the name; its 201 Location is followed, then the goForm anchor */
    public function testFollowsTheFormsAndAnchorsOfAnHtmlPage(): void
    {
        $resource = new HttpResource('127.0.0.1:8089', dirname(__DIR__) . '/Fake/app/public-twig/index.php', __DIR__ . '/log');

        $form = $resource->get('/form#link=self:doCreate,self:goForm');

        $this->assertSame(200, $form->code);
        $this->assertStringContainsString('class="doCreate"', (string) $form->view);
    }
}
