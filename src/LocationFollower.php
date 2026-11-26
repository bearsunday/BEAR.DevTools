<?php

declare(strict_types=1);

namespace BEAR\Dev;

use BEAR\Resource\Code;
use BEAR\Resource\Exception\LinkException;
use BEAR\Resource\FactoryInterface;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\Method;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;

use function preg_match;
use function sprintf;
use function strtolower;

/**
 * The resource that answers a response, and the method it was requested with
 *
 * A 303, or a 201 with a Location, is answered by the resource its Location names, requested once with GET.
 *
 * @internal
 */
final class LocationFollower
{
    /** A Location a link follows: a path, resolved against the scheme and host of the response */
    private const LOCATION_PATH = '#\A/(?!/)#';

    public function __construct(
        private readonly InvokerInterface $invoker,
        private readonly FactoryInterface $factory,
    ) {
    }

    /**
     * @return array{ResourceObject, Method}
     *
     * @throws LinkException
     */
    public function __invoke(ResourceObject $response, Method $method): array
    {
        if (! $this->redirects($response)) {
            return [$response, $method];
        }

        $location = $this->location($response);
        if ($location === null || preg_match(self::LOCATION_PATH, $location) !== 1) {
            throw new LinkException(sprintf('%s answered %d with the Location %s; a link follows only a path', (string) $response->uri, $response->code, $location ?? '(none)'), 500);
        }

        $ro = $this->factory->newInstance("{$response->uri->scheme}://{$response->uri->host}{$location}");
        $located = $this->invoker->invoke(new Request($this->invoker, $ro, Method::GET, $ro->uri->query));
        if ($this->redirects($located)) {
            throw new LinkException(sprintf('%s, the Location of %s, redirects again; a link follows one Location', (string) $located->uri, (string) $response->uri), 500);
        }

        return [$located, Method::GET];
    }

    /** A 303, or a 201 that names the created resource in a Location */
    private function redirects(ResourceObject $ro): bool
    {
        return $ro->code === Code::SEE_OTHER || ($ro->code === Code::CREATED && $this->location($ro) !== null);
    }

    /** The Location header of a response, whatever the case of its name, as HTTP reads it */
    private function location(ResourceObject $ro): string|null
    {
        foreach ($ro->headers as $name => $value) {
            if (strtolower($name) === 'location') {
                return $value;
            }
        }

        return null;
    }
}
