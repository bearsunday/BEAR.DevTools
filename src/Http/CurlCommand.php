<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\Types;

use function array_keys;
use function array_map;
use function escapeshellarg;
use function implode;
use function preg_match;
use function sprintf;
use function strlen;

/**
 * The curl command a request is logged as, to be pasted into a shell
 *
 * @internal
 * @psalm-import-type MultipartFields from Types
 */
final class CurlCommand
{
    private const LOGGED_ARG_MAX = 8000;

    /** A header name that names a credential, whose value the command hides */
    private const SECRET_HEADER = '/auth|token|key|secret|cookie|session|passw/i';
    private const HIDDEN_VALUE = '***';

    /** The `-H` arguments of the headers sent with every request */
    private readonly string $headerArgs;

    /** @param array<string, string> $headers sent with every request, as name => value */
    public function __construct(
        array $headers,
        private readonly MultipartForm $multipartForm,
    ) {
        $this->headerArgs = implode('', array_map(
            static fn (string $name, string $value): string => ' -H ' . escapeshellarg($name . ': ' . (preg_match(self::SECRET_HEADER, $name) === 1 ? self::HIDDEN_VALUE : $value)),
            array_keys($headers),
            $headers,
        ));
    }

    public function get(string $url): string
    {
        return sprintf('curl -s -i%s %s', $this->headerArgs, $this->arg($url));
    }

    public function json(string $method, string $url, string $json): string
    {
        return sprintf(
            'curl -s -i%s -H %s -X %s -d %s %s',
            $this->headerArgs,
            escapeshellarg('Content-Type:application/json'),
            escapeshellarg($method),
            $this->arg($json),
            $this->arg($url),
        );
    }

    /** @param MultipartFields $fields */
    public function multipart(string $method, string $url, array $fields): string
    {
        $formArgs = implode('', array_map(fn (array $arg): string => ' ' . $arg[0] . ' ' . $this->arg($arg[1]), $this->multipartForm->curlArgs($fields)));

        return sprintf('curl -s -i%s -X %s%s %s', $this->headerArgs, escapeshellarg($method), $formArgs, $this->arg($url));
    }

    /**
     * escapeshellarg() refuses arguments longer than the platform command line limit (about 8 KB on Windows,
     * ARG_MAX elsewhere, e.g. 1 MiB on macOS): a fatal error, or a ValueError on PHP 8.4+. Such an argument
     * cannot be pasted into a shell anyway, so the command shows its size instead.
     */
    private function arg(string $arg): string
    {
        if (strlen($arg) > self::LOGGED_ARG_MAX) {
            return escapeshellarg(sprintf('<%d bytes omitted>', strlen($arg)));
        }

        return escapeshellarg($arg);
    }
}
