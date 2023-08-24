<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Resource\NullResourceObject;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri;

use function array_key_exists;
use function array_pop;
use function array_shift;
use function assert;
use function implode;
use function is_string;
use function json_decode;
use function preg_match;

use const PHP_EOL;

/**
 * Create ResourceObject from curl output
 */
final class CreateResponse
{
    /**
     * @param array<string> $output
     */
    public function __invoke(Uri $uri, array $output): ResourceObject
    {
        $headers = $body = [];
        $status = (string) array_shift($output);
        do {
            $line = array_shift($output);
            assert(is_string($line));
            $headers[] = $line;
        } while ($line !== '');

        do {
            $line = array_shift($output);
            $body[] = (string) $line;
        } while ($line !== null);

        $ro = new NullResourceObject();
        $ro->uri = $uri;
        $ro->code = $this->getCode($status);
        $ro->headers = $this->getHeaders($headers);
        $view = $this->getJsonView($body);
        $ro->body = (array) json_decode($view);
        $ro->view = $view;

        return $ro;
    }

    private function getCode(string $status): int
    {
        preg_match('/\d{3}/', $status, $match);
        assert(array_key_exists(0, $match));

        return (int) $match[0];
    }

    /**
     * @param array<string> $headers
     *
     * @return array<string, string>
     */
    private function getHeaders(array $headers): array
    {
        $keyedHeader = [];
        array_pop($headers);
        foreach ($headers as $header) {
            preg_match('/(.+):\s(.+)/', $header, $matched);
            assert(array_key_exists(1, $matched));
            assert(array_key_exists(2, $matched));
            $keyedHeader[$matched[1]] = $matched[2];
        }

        return $keyedHeader;
    }

    /**
     * @param array<string> $body
     */
    private function getJsonView(array $body): string
    {
        array_pop($body);

        return implode(PHP_EOL, $body);
    }
}
