<?php

declare(strict_types=1);

namespace BEAR\Dev;

use BEAR\Resource\Exception\LinkException;
use BEAR\Resource\Exception\LinkRelException;
use Throwable;

/** Stories of the fake app, each written as one URI, that a resource client follows in process, over HAL and over HTML */
final class LinkStories
{
    /**
     * Stories any representation can follow: links, and forms that POST
     *
     * @return array<string, array{string, string, array<string, string>}> the URI, and the path and query it ends at
     */
    public static function anyRepresentation(): array
    {
        return [
            'POST a form and follow its 201 Location' => ['/form#link=self:doCreate?name=koriym', '/item', ['id' => '5']],
            'then follow a link back' => ['/form#link=self:doCreate?name=koriym,self:goForm', '/form', []],
        ];
    }

    /**
     * Stories that need a method an HTML form does not have
     *
     * @return array<string, array{string, string, array<string, string>}> the URI, and the path and query it ends at
     */
    public static function apiOnly(): array
    {
        return [
            'PUT a link and follow its 303 Location' => ['/form#link=self:doUpdate?id=3', '/item', ['id' => '3']],
            'create, come back and update' => ['/form#link=self:doCreate?name=koriym,self:goForm,self:doUpdate?id=3', '/item', ['id' => '3']],
        ];
    }

    /** @return array<string, array{string, class-string<Throwable>}> the URI and what it throws */
    public static function unfollowable(): array
    {
        return [
            'a rel the representation does not carry' => ['/form#link=self:missing', LinkRelException::class],
            'a 303 without a Location' => ['/form#link=self:doLoseLocation', LinkException::class],
            'a Location that is not a path' => ['/form#link=self:doLeave', LinkException::class],
            'a located resource that redirects again' => ['/form#link=self:doRedirectTwice', LinkException::class],
        ];
    }
}
