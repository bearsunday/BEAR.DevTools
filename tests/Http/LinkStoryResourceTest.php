<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Resource\ResourceInterface;
use MyVendor\MyProject\Injector;

use function assert;

/** The stories followed in process, where bear/resource reads each link from its #[Link] */
final class LinkStoryResourceTest extends AbstractApiLinkStoryTest
{
    protected function newResource(): ResourceInterface
    {
        $resource = Injector::getInstance('test-app')->getInstance(ResourceInterface::class);
        assert($resource instanceof ResourceInterface);

        return $resource;
    }
}
