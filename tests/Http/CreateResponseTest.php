<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Resource\Uri;
use PHPUnit\Framework\TestCase;

final class CreateResponseTest extends TestCase
{
    public function testHeaderValueContainingColonSpace(): void
    {
        $ro = (new CreateResponse())(new Uri('http://127.0.0.1/'), [
            'HTTP/1.1 200 OK',
            'Link: </next>; rel="goNext"; title="Step: 2"',
            'Content-Type: application/json',
            '',
            '{}',
        ]);

        $this->assertSame('</next>; rel="goNext"; title="Step: 2"', $ro->headers['Link']);
        $this->assertSame('application/json', $ro->headers['Content-Type']);
    }
}
