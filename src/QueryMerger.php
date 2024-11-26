<?php

declare(strict_types=1);

namespace BEAR\Dev;

use function parse_str;
use function parse_url;

use const PHP_URL_PATH;
use const PHP_URL_QUERY;

final class QueryMerger
{
    /** @param array<string, mixed> $query */
    public function __invoke(string $uri, array $query): Uri
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $uriQueryString = (string) parse_url($uri, PHP_URL_QUERY);
        parse_str($uriQueryString, $uriQuery);
        $uriQueryKeyString = [];
        foreach ($uriQuery as $key => $value) {
            $uriQueryKeyString[(string) $key] = $value;
        }

        $mergedQuery = $uriQueryKeyString + $query;

        return new Uri($path, $mergedQuery);
    }
}
