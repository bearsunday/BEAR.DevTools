<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\QueryMerger;
use BEAR\Resource\RequestInterface;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri as ResourceUri;
use Koriym\PhpServer\PhpServer;
use LogicException;
use Override;

use function curl_close;
use function curl_exec;
use function curl_init;
use function curl_setopt;
use function escapeshellarg;
use function explode;
use function file_exists;
use function file_put_contents;
use function http_build_query;
use function implode;
use function in_array;
use function is_string;
use function json_encode;
use function sprintf;
use function strtoupper;
use function trim;

use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HEADER;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const FILE_APPEND;
use const JSON_THROW_ON_ERROR;
use const PHP_EOL;

final class HttpResource implements ResourceInterface
{
    /** @var string */
    private $logFile;

    /** @var string */
    private $baseUri;

    /** @var PhpServer */
    private static $server;

    /** @var QueryMerger */
    private $queryMerger;

    /** @var CreateResponse */
    private $createResponse;

    public function __construct(string $host, string $index, string $logFile = 'php://stderr')
    {
        $this->baseUri = sprintf('http://%s', $host);
        $this->logFile = $logFile;
        $this->resetLog($logFile);

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

    private function resetLog(string $logFile): void
    {
        /** @var array<string> $started */
        static $started = [];

        if (in_array($logFile, $started) || ! file_exists($logFile)) {
            return;
        }

        file_put_contents($logFile, '');
        $started[] = $logFile;
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
    public function href(string $rel, array $query = []): ResourceObject
    {
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
        $responseLog = implode(PHP_EOL, $output);
        $log = sprintf("%s\n\n%s", $curlCommand, $responseLog) . PHP_EOL . PHP_EOL;
        file_put_contents($this->logFile, $log, FILE_APPEND);
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
        curl_close($ch);

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
