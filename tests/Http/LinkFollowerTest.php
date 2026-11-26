<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\QueryMerger;
use BEAR\Resource\NullResourceObject;
use BEAR\Resource\ResourceObject;
use PHPUnit\Framework\TestCase;

use function array_shift;

final class LinkFollowerTest extends TestCase
{
    /** @var list<array{string, string, array<string, mixed>}> */
    private array $sent = [];
    private LinkFollower $follower;

    protected function setUp(): void
    {
        $this->follower = new LinkFollower(new QueryMerger());
    }

    public function testAClientErrorEndsTheChainWithThatResponse(): void
    {
        $notFound = $this->ro(404);
        $ro = $this->follow('GET', '/start#link=self:next,self:more', [
            $this->ro(200, ['next' => '/next']),
            $notFound,
        ]);

        $this->assertSame($notFound, $ro);
        $this->assertCount(2, $this->sent);
    }

    public function testTheLinkQueryWinsOverAHiddenInput(): void
    {
        $this->follow('GET', '/form#link=self:doCreate?name=bear', [
            $this->ro(200, [], '<form class="doCreate" method="post" action="/item"><input type="hidden" name="name" value="koriym"></form>'),
            $this->ro(200),
        ]);

        $this->assertSame(['POST', '/item', ['name' => 'bear']], $this->sent[1]);
    }

    public function testHiddenInputsAreSentInTheStructureOfTheirNames(): void
    {
        $this->follow('GET', '/form#link=self:doTag', [
            $this->ro(200, [], '<form class="doTag" method="post" action="/item">'
                . '<input type="hidden" name="tags[]" value="a"><input type="hidden" name="tags[]" value="b">'
                . '<input type="hidden" name="user[name]" value="koriym"></form>'),
            $this->ro(200),
        ]);

        $this->assertSame(['POST', '/item', ['tags' => ['a', 'b'], 'user' => ['name' => 'koriym']]], $this->sent[1]);
    }

    public function testTheLinkQueryReplacesAHiddenInputByItsTopLevelName(): void
    {
        $this->follow('GET', '/form#link=self:doTag?user%5Bname%5D=bear', [
            $this->ro(200, [], '<form class="doTag" method="post" action="/item">'
                . '<input type="hidden" name="user[name]" value="koriym"><input type="hidden" name="user[role]" value="admin"></form>'),
            $this->ro(200),
        ]);

        $this->assertSame(['POST', '/item', ['user' => ['name' => 'bear']]], $this->sent[1]);
    }

    public function testTheFirstResponseIsLocatedBeforeItsRelIsRead(): void
    {
        $created = $this->ro(201);
        $created->headers = ['Location' => '/item?id=5'];
        $this->follow('POST', '/item?name=x#link=self:goForm', [
            $created,
            $this->ro(200, ['goForm' => '/form']),
            $this->ro(200),
        ]);

        $this->assertSame([['POST', '/item?name=x#link=self:goForm', []], ['GET', '/item?id=5', []], ['GET', '/form', []]], $this->sent);
    }

    public function testHrefDropsTheFragmentOfTheHref(): void
    {
        $this->assertSame('/next?page=2', $this->follower->href('next', $this->ro(200, ['next' => '/next?page=2#comments'])));
    }

    /** @param list<ResourceObject> $responses the responses to the requests, in order */
    private function follow(string $method, string $uri, array $responses): ResourceObject
    {
        $send = function (string $method, string $uri, array $query) use (&$responses): ResourceObject {
            $this->sent[] = [$method, $uri, $query];
            $response = array_shift($responses);
            $this->assertInstanceOf(ResourceObject::class, $response, 'More requests than responses');

            return $response;
        };

        return ($this->follower)($method, $uri, [], $send(...));
    }

    /** @param array<string, string> $links HAL links, as rel => href */
    private function ro(int $code, array $links = [], string $view = ''): NullResourceObject
    {
        $ro = new NullResourceObject();
        $ro->code = $code;
        $ro->body = [];
        foreach ($links as $rel => $href) {
            $ro->body['_links'][$rel] = ['href' => $href];
        }

        $ro->view = $view;

        return $ro;
    }
}
