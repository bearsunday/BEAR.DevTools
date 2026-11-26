<?php

declare(strict_types=1);

namespace BEAR\Dev;

use BEAR\Dev\LinkerApp\Resource\App\Drink\Mocha;
use BEAR\Dev\LinkerApp\Resource\App\Form;
use BEAR\Dev\LinkerApp\Resource\App\Item;
use BEAR\Dev\LinkerApp\Resource\App\Payment;
use BEAR\Resource\Code;
use BEAR\Resource\Exception\LinkException;
use BEAR\Resource\Exception\LinkQueryException;
use BEAR\Resource\Exception\LinkRelException;
use BEAR\Resource\LinkerInterface;
use BEAR\Resource\Module\ResourceModule;
use BEAR\Resource\ResourceInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;

use function assert;
use function json_encode;

final class DeclaredMethodLinkerTest extends TestCase
{
    private ResourceInterface $resource;

    protected function setUp(): void
    {
        $module = new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new ResourceModule('BEAR\Dev\LinkerApp'));
                $this->bind(LinkerInterface::class)->to(DeclaredMethodLinker::class);
            }
        };
        $this->resource = (new Injector($module, __DIR__ . '/tmp'))->getInstance(ResourceInterface::class);
    }

    public function testALinkIsRequestedWithTheMethodItDeclaresAndItsQuery(): void
    {
        $ro = $this->resource->post('app://self/order?drink=latte#link=self:payment?card=1234');

        $this->assertInstanceOf(Payment::class, $ro);
        $this->assertSame(Code::CREATED, $ro->code);
        $this->assertSame('put', $ro->uri->method);
        $this->assertSame('app://self/payment?id=7&card=1234', (string) $ro->uri);
        $this->assertSame(['id' => 7, 'card' => '1234'], $ro->body);
    }

    public function testTheNextLinkIsReadFromTheMethodTheLinkedResourceWasRequestedWith(): void
    {
        $ro = $this->resource->post('app://self/order?drink=latte#link=self:payment?card=1234,self:order');

        $this->assertSame('get', $ro->uri->method);
        $this->assertSame(['id' => 7, 'drink' => 'latte'], $ro->body);
    }

    public function testANewLinkAddsTheBodyOfTheResourceRequestedWithTheDeclaredMethod(): void
    {
        $ro = $this->resource->post('app://self/order?drink=latte#link=new:payment?card=1234');

        $this->assertSame(['id' => 7, 'drink' => 'latte', 'payment' => ['id' => 7, 'card' => '1234']], $ro->body);
    }

    /** @return array<string, array{string, string}> */
    public static function locationProvider(): array
    {
        return [
            'a 201 with a Location' => ['app://self/form#link=self:create?name=koriym', 'app://self/item?id=5'],
            'a 303' => ['app://self/form#link=self:update?id=3', 'app://self/item?id=3'],
            'a Location header named in lower case' => ['app://self/form#link=self:lowerCaseLocation', 'app://self/item?id=4'],
            'a method declared in upper case' => ['app://self/form#link=self:createInUpperCase?name=koriym', 'app://self/item?id=5'],
        ];
    }

    #[DataProvider('locationProvider')]
    public function testALinkAnsweredWithALocationReturnsTheLocatedResource(string $uri, string $located): void
    {
        $ro = $this->resource->get($uri);

        $this->assertInstanceOf(Item::class, $ro);
        $this->assertSame(Code::OK, $ro->code);
        $this->assertSame('get', $ro->uri->method);
        $this->assertSame($located, (string) $ro->uri);
    }

    /** `form` is declared only on Item::onGet() and onDelete() */
    public function testTheLinkAfterALocationIsReadFromTheLocatedResource(): void
    {
        $this->assertInstanceOf(Form::class, $this->resource->get('app://self/form#link=self:create?name=koriym,self:form'));
    }

    public function testARequestAnsweredWithALocationIsLocatedBeforeItsFirstLink(): void
    {
        $this->assertInstanceOf(Form::class, $this->resource->post('app://self/item?name=koriym#link=self:form'));
    }

    public function testANewLinkAnsweredWithALocationAddsTheLocatedBody(): void
    {
        $ro = $this->resource->get('app://self/form#link=new:create?name=koriym');

        $this->assertSame(['id' => 7, 'create' => ['id' => 5]], $ro->body);
    }

    public function testAClientErrorEndsTheChainWithThatResponse(): void
    {
        $ro = $this->resource->get('app://self/form#link=self:fail,self:form');

        $this->assertInstanceOf(Item::class, $ro);
        $this->assertSame(Code::BAD_REQUEST, $ro->code);
    }

    public function testAMissingRelNamesTheMethodAndTheRelsItDeclares(): void
    {
        $this->expectException(LinkRelException::class);
        $this->expectExceptionMessage('rel:nope class:BEAR\Dev\LinkerApp\Resource\App\Form method:onGet declared:create,');

        $this->resource->get('app://self/form#link=self:nope');
    }

    /** @return array<string, array{string}> */
    public static function unfollowableLocationProvider(): array
    {
        return [
            'a 303 without a Location' => ['app://self/form#link=self:noLocation'],
            'a Location that is not a path' => ['app://self/form#link=self:leave'],
            'a network-path Location' => ['app://self/form#link=self:networkPath'],
            'a located resource that redirects again' => ['app://self/form#link=self:redirectTwice'],
        ];
    }

    #[DataProvider('unfollowableLocationProvider')]
    public function testALocationALinkCannotFollowThrows(string $uri): void
    {
        $this->expectException(LinkException::class);

        $this->resource->get($uri);
    }

    public function testAClientErrorOfTheRequestEndsItBeforeItsFirstLink(): void
    {
        $ro = $this->resource->delete('app://self/item#link=self:form');

        $this->assertInstanceOf(Item::class, $ro);
        $this->assertSame(Code::BAD_REQUEST, $ro->code);
    }

    public function testALinkQueryFillsAPathVariableOfTheHref(): void
    {
        $this->assertInstanceOf(Mocha::class, $this->resource->post('app://self/order?drink=latte#link=self:drink?drink=mocha'));
    }

    public function testALinkDeclaredWithAMethodAResourceDoesNotTakeThrows(): void
    {
        $this->expectException(LinkException::class);
        $this->expectExceptionMessage("rel:purge class:BEAR\\Dev\\LinkerApp\\Resource\\App\\Form declares the method 'purge'");

        $this->resource->get('app://self/form#link=self:purge');
    }

    /** @return array<string, array{string}> */
    public static function crawlProvider(): array
    {
        return [
            'a body' => ['app://self/author?id=1#link=crawl:tree'],
            'a list' => ['app://self/authors#link=crawl:tree'],
        ];
    }

    #[DataProvider('crawlProvider')]
    public function testACrawlLinkIsFollowedAsTheResourceClientFollowsIt(string $uri): void
    {
        $body = $this->resource->get($uri)->body;

        $this->assertStringContainsString('"posts":', (string) json_encode($body));
        $this->assertSame($this->resourceClient()->get($uri)->body, $body);
    }

    public function testACrawlLinkFromABodyThatIsNotAnArrayThrows(): void
    {
        $this->expectException(LinkQueryException::class);

        $this->resource->get('app://self/scalar#link=crawl:tree');
    }

    /** The resource client with its own linker, which follows every link with GET */
    private function resourceClient(): ResourceInterface
    {
        $resource = (new Injector(new ResourceModule('BEAR\Dev\LinkerApp'), __DIR__ . '/tmp'))->getInstance(ResourceInterface::class);
        assert($resource instanceof ResourceInterface);

        return $resource;
    }
}
