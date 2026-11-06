<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\Http\Exception\HalLinkNotFoundException;
use BEAR\Dev\Http\Exception\HttpRequestException;
use BEAR\Dev\QueryMerger;
use BEAR\Resource\RequestInterface;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri as ResourceUri;
use Koriym\PhpServer\PhpServer;
use LogicException;
use Override;

use function curl_error;
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
use function is_string;
use function json_encode;
use function preg_replace;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function trim;

use const CURLOPT_CONNECTTIMEOUT;
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
    private const CONNECT_TIMEOUT = 10;

    private string $logPath;
    private string $baseUri;

    /** @var array<string, PhpServer> Servers started per host and index, kept alive for the whole process */
    private static array $servers = [];
    private readonly QueryMerger $queryMerger;
    private readonly CreateResponse $createResponse;
    private readonly HrefExtractor $hrefExtractor;

    public function __construct(string $host, string $index, string $logPath = 'php://stderr')
    {
        $this->baseUri = sprintf('http://%s', $host);
        $this->logPath = $logPath;

        $this->startServer($host, $index);
        $this->queryMerger = new QueryMerger();
        $this->createResponse = new CreateResponse();
        $this->hrefExtractor = new HrefExtractor();
    }

    private function startServer(string $host, string $index): void
    {
        $id = $host . $index;
        if (isset(self::$servers[$id])) {
            return;
        }

        $server = new PhpServer($host, $index);
        $server->start();
        self::$servers[$id] = $server;
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
        if (! $ro instanceof ResourceObject) {
            throw new HalLinkNotFoundException('A source ResourceObject is required to follow a link.');
        }

        $href = $this->hrefExtractor->href($rel, $ro);
        if ($href === null) {
            throw new HalLinkNotFoundException(sprintf('Link rel `%s` is not available.', $rel));
        }

        return $this->get($href, $query);
    }

    /** @param array<string, mixed> $query */
    #[Override]
    public function newRequest(object|string $method, string $uri, array $query = []): RequestInterface
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
        try {
            $output = $this->executeCurl($url, $method, $body);
        } catch (HttpRequestException $e) {
            $this->log([$e->getMessage()], $curlCommand);

            throw $e;
        }

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
            throw new HttpRequestException(sprintf('Could not initialize cURL for %s', $url)); // @codeCoverageIgnore
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        // Only the connection is time-limited: a slow response (or a server paused in a debugger) is waited for.
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);

        $upperMethod = strtoupper($method);
        if ($upperMethod !== 'GET' && $upperMethod !== '') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $upperMethod);
        }

        // An empty "Expect:" stops cURL from waiting for "100 Continue" on large bodies.
        $headers = ['Expect:'];
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $headers[] = 'Content-Type:application/json';
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);

        if (! is_string($response)) {
            throw new HttpRequestException(sprintf('%s %s failed: %s', $upperMethod, $url, curl_error($ch)));
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
