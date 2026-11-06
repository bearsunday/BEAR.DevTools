<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\Http\Exception\HttpRequestException;
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

    public function testHeaderWithoutSpaceAndEmptyHeader(): void
    {
        $ro = (new CreateResponse())(new Uri('http://127.0.0.1/'), [
            'HTTP/1.1 200 OK',
            'Link:</next>; rel="goNext"',
            'X-Empty:',
            '',
            '{}',
        ]);

        $this->assertSame('</next>; rel="goNext"', $ro->headers['Link']);
        $this->assertSame('', $ro->headers['X-Empty']);
    }

    public function testInterimContinueResponseIsSkipped(): void
    {
        $ro = (new CreateResponse())(new Uri('http://127.0.0.1/'), [
            'HTTP/1.1 100 Continue',
            '',
            'HTTP/1.1 201 Created',
            'Location: /users/1',
            '',
            '{"id": 1}',
        ]);

        $this->assertSame(201, $ro->code);
        $this->assertSame(['Location' => '/users/1'], $ro->headers);
        $this->assertSame('{"id": 1}', $ro->view);
    }

    public function testSwitchingProtocolsIsFinal(): void
    {
        $ro = (new CreateResponse())(new Uri('http://127.0.0.1/'), [
            'HTTP/1.1 101 Switching Protocols',
            'Upgrade: websocket',
            '',
        ]);

        $this->assertSame(101, $ro->code);
        $this->assertSame(['Upgrade' => 'websocket'], $ro->headers);
    }

    public function testSeveralInterimResponsesAreSkipped(): void
    {
        $ro = (new CreateResponse())(new Uri('http://127.0.0.1/'), [
            'HTTP/1.1 100 Continue',
            '',
            'HTTP/1.1 103 Early Hints',
            'Link: </style.css>; rel=preload',
            '',
            'HTTP/1.1 200 OK',
            'X-A: 1',
            '',
            '{"a":1}',
        ]);

        $this->assertSame(200, $ro->code);
        $this->assertSame(['X-A' => '1'], $ro->headers);
        $this->assertSame('{"a":1}', $ro->view);
    }

    public function testMissingFinalStatusLineThrows(): void
    {
        $this->expectException(HttpRequestException::class);

        (new CreateResponse())(new Uri('http://127.0.0.1/'), ['HTTP/1.1 100 Continue', '']);
    }
}
