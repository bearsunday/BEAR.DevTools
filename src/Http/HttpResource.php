<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\Http\Exception\HalLinkNotFoundException;
use BEAR\Dev\QueryMerger;
use BEAR\Resource\Method;
use BEAR\Resource\RequestInterface;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri as ResourceUri;
use Koriym\PhpServer\PhpServer;
use LogicException;
use Override;

use function array_key_exists;
use function curl_exec;
use function curl_init;
use function curl_setopt;
use function debug_backtrace;
use function escapeshellarg;
use function explode;
use function file_exists;
use function file_put_contents;
use function http_build_query;
use function implode;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function json_encode;
use function preg_replace;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function trim;

use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HEADER;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const DEBUG_BACKTRACE_IGNORE_ARGS;
use const DIRECTORY_SEPARATOR;
use const FILE_APPEND;
use const JSON_THROW_ON_ERROR;
use const PHP_EOL;

final class HttpResource implements ResourceInterface
{
    private string $logPath;
    private string $baseUri;
    private static PhpServer $server;
    private readonly QueryMerger $queryMerger;
    private readonly CreateResponse $createResponse;

    public function __construct(string $host, string $index, string $logPath = 'php://stderr')
    {
        $this->baseUri = sprintf('http://%s', $host);
        $this->logPath = $logPath;

        $this->startServer($host, $index);
        $this->queryMerger = new QueryMerger();
        $this->createResponse = new CreateResponse();
    }

    private function startServer(string $host, string $index): void
    {
        /** @var array<string> $started */
        static $started = [];

        $id = $host . $index;
        if (in_array($id, $started)) {
            return;
        }

        self::$server = new PhpServer($host, $index);
        self::$server->start();
        $started[] = $id;
    }

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    #[Override]
    public function newInstance($uri): ResourceObject
    {
        throw new LogicException();
    }

    /** @codeCoverageIgnore */
    #[Override]
    public function object(ResourceObject $ro): RequestInterface
    {
        unset($ro);

        throw new LogicException();
    }

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    #[Override]
    public function uri($uri): RequestInterface
    {
        throw new LogicException();
    }

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    #[Override]
    public function href(string $rel, array $query = [], ResourceObject|null $ro = null): ResourceObject
    {
        if ($ro === null || ! is_array($ro->body)) {
            throw new HalLinkNotFoundException('A source ResourceObject with HAL links is required.');
        }

        $links = $ro->body['_links'] ?? null;
        if (is_object($links)) {
            $links = (array) $links;
        }

        if (! is_array($links) || ! array_key_exists($rel, $links)) {
            throw new HalLinkNotFoundException(sprintf('HAL link rel `%s` is not available.', $rel));
        }

        $link = $links[$rel];
        if (is_object($link)) {
            $link = (array) $link;
        }

        $href = is_array($link) ? ($link['href'] ?? null) : null;
        if (! is_string($href)) {
            throw new HalLinkNotFoundException(sprintf('HAL link rel `%s` has no href.', $rel));
        }

        return $this->get($href, $query);
    }

    /** @param array<string, mixed> $query */
    #[Override]
    public function newRequest(Method $method, string $uri, array $query = []): RequestInterface
    {
        unset($method, $uri, $query);

        throw new LogicException();
    }

    /** @param array<string, mixed> $query */
    #[Override]
    public function crawl(string $uri, string $linkKey, array $query = []): ResourceObject
    {
        unset($uri, $linkKey, $query);

        throw new LogicException();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function get(string $uri, array $query = []): ResourceObject
    {
        return $this->safeRequest($uri, $query);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function post(string $uri, array $query = []): ResourceObject
    {
        return $this->unsafeRequest('POST', $uri, $query);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function put(string $uri, array $query = []): ResourceObject
    {
        return $this->unsafeRequest('PUT', $uri, $query);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function patch(string $uri, array $query = []): ResourceObject
    {
        return $this->unsafeRequest('PATCH', $uri, $query);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function delete(string $uri, array $query = []): ResourceObject
    {
        return $this->unsafeRequest('DELETE', $uri, $query);
    }

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    #[Override]
    public function head(string $uri, array $query = []): ResourceObject
    {
        throw new LogicException();
    }

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    #[Override]
    public function options(string $uri, array $query = []): ResourceObject
    {
        throw new LogicException();
    }

    /** @param array<string, mixed> $query */
    private function safeRequest(string $path, array $query): ResourceObject
    {
        $uri = ($this->queryMerger)($path, $query);
        $queryParameter = $uri->query ? '?' . http_build_query($uri->query) : '';
        $url = sprintf('%s%s%s', $this->baseUri, $uri->path, $queryParameter);
        $curlCommand = sprintf('curl -s -i %s', escapeshellarg($url));

        return $this->request($url, 'get', $curlCommand);
    }

    /** @param array<string, mixed> $query */
    private function unsafeRequest(string $method, string $path, array $query): ResourceObject
    {
        $uri = ($this->queryMerger)($path, $query);
        $json = json_encode($uri->query, JSON_THROW_ON_ERROR);
        $url = sprintf('%s%s', $this->baseUri, $uri->path);

        $curlCommand = sprintf(
            'curl -s -i -H %s -X %s -d %s %s',
            escapeshellarg('Content-Type:application/json'),
            escapeshellarg($method),
            escapeshellarg($json),
            escapeshellarg($url),
        );

        return $this->request($url, $method, $curlCommand, $json);
    }

    /** @param array<string> $output */
    public function log(array $output, string $curlCommand): void
    {
        $logFile = $this->logFile();
        $this->resetLog($logFile);
        $responseLog = implode(PHP_EOL, $output);
        $log = sprintf("%s\n\n%s", $curlCommand, $responseLog) . PHP_EOL . PHP_EOL;
        file_put_contents($logFile, $log, FILE_APPEND);
    }

    private function logFile(): string
    {
        if ($this->logPath === 'php://stderr' || str_ends_with($this->logPath, '.log')) {
            return $this->logPath;
        }

        return $this->logPath . DIRECTORY_SEPARATOR . $this->currentTestLogName();
    }

    private function currentTestLogName(): string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $function = $frame['function'];
            if (! str_starts_with($function, 'test')) {
                continue;
            }

            return self::kebabCase($function) . '.log';
        }

        return 'http-resource.log';
    }

    private static function kebabCase(string $name): string
    {
        $kebab = preg_replace('/(?<!^)[A-Z]/', '-$0', $name) ?? $name;

        return strtolower($kebab);
    }

    private function resetLog(string $logFile): void
    {
        /** @var array<string> $reset */
        static $reset = [];

        if ($logFile === 'php://stderr' || in_array($logFile, $reset, true)) {
            return;
        }

        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }

        $reset[] = $logFile;
    }

    public function request(string $url, string $method, string $curlCommand, string|null $body = null): ResourceObject
    {
        $output = $this->executeCurl($url, $method, $body);
        $uri = new ResourceUri($url);
        $uri->method = $method;
        $ro = ($this->createResponse)($uri, $output);
        $this->log($output, $curlCommand);

        return $ro;
    }

    /**
     * Execute HTTP request using PHP's native cURL extension
     *
     * @return array<string>
     */
    private function executeCurl(string $url, string $method, string|null $body = null): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return []; // @codeCoverageIgnore
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $upperMethod = strtoupper($method);
        if ($upperMethod !== 'GET' && $upperMethod !== '') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $upperMethod);
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        }

        $response = curl_exec($ch);

        if (! is_string($response)) {
            return [];
        }

        return $this->parseResponse($response);
    }

    /**
     * Parse cURL response into lines array
     *
     * @return array<string>
     */
    private function parseResponse(string $response): array
    {
        $lines = explode("\n", $response);
        $result = [];
        foreach ($lines as $line) {
            $trimmed = trim($line, "\r");
            $result[] = $trimmed;
        }

        return $result;
    }
}
