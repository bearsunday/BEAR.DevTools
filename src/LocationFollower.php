<?php

declare(strict_types=1);

namespace BEAR\Dev;

use BEAR\Resource\Code;
use BEAR\Resource\Exception\LinkException;
use BEAR\Resource\ResourceObject;
use Closure;

use function preg_match;
use function sprintf;
use function strtolower;

/**
 * The resource that answers a response a link reached, in process and over HTTP alike
 *
 * A 303, or a 201 with a Location, is answered by the resource its Location names, requested once with GET.
 *
 * @internal
 */
final class LocationFollower
{
    /** A Location a link follows: a path, on the server of the response */
    private const LOCATION_PATH = '#\A/(?!/)#';

    /**
     * @param Closure(string): ResourceObject $get requests a path on the server of the response with GET
     *
     * @return ResourceObject the response itself, or the resource its Location names
     *
     * @throws LinkException
     */
    public function __invoke(ResourceObject $response, Closure $get): ResourceObject
    {
        if (! $this->redirects($response)) {
            return $response;
        }

        $location = $this->location($response);
        if ($location === null || preg_match(self::LOCATION_PATH, $location) !== 1) {
            throw new LinkException(sprintf('%s answered %d with the Location %s; a link follows only a path', (string) $response->uri, $response->code, $location ?? '(none)'), 500);
        }

        $located = $get($location);
        if ($this->redirects($located)) {
            throw new LinkException(sprintf('%s, the Location of %s, redirects again; a link follows one Location', (string) $located->uri, (string) $response->uri), 500);
        }

        return $located;
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
