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

use function exec;
use function file_exists;
use function file_put_contents;
use function http_build_query;
use function implode;
use function in_array;
use function json_encode;
use function sprintf;

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
    public function newInstance($uri): ResourceObject
    {
        throw new LogicException();
    }

    /**
     * @codeCoverageIgnore
     */
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
    public function uri($uri): RequestInterface
    {
        throw new LogicException();
    }

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    public function href(string $rel, array $query = []): ResourceObject
    {
        throw new LogicException();
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $uri, array $query = []): ResourceObject
    {
        return $this->safeRequest($uri, $query);
    }

    /**
     * {@inheritDoc}
     */
    public function post(string $uri, array $query = []): ResourceObject
    {
        return $this->unsafeRequest('POST', $uri, $query);
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $uri, array $query = []): ResourceObject
    {
        return $this->unsafeRequest('PUT', $uri, $query);
    }

    /**
     * {@inheritDoc}
     */
    public function patch(string $uri, array $query = []): ResourceObject
    {
        return $this->unsafeRequest('PATCH', $uri, $query);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $uri, array $query = []): ResourceObject
    {
        return $this->unsafeRequest('DELETE', $uri, $query);
    }

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    public function head(string $uri, array $query = []): ResourceObject
    {
        throw new LogicException();
    }

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    public function options(string $uri, array $query = []): ResourceObject
    {
        throw new LogicException();
    }

    /**
     * @param array<mixed> $query
     */
    private function safeRequest(string $path, array $query): ResourceObject
    {
        $uri = ($this->queryMerger)($path, $query);
        $queryParameter = $uri->query ? '?' . http_build_query($uri->query) : '';
        $url = sprintf('%s%s%s', $this->baseUri, $uri->path, $queryParameter);
        $curl = sprintf("curl -s -i '%s'", $url);

        return $this->request($curl, 'get', $url);
    }

    /**
     * @param array<mixed> $query
     */
    private function unsafeRequest(string $method, string $path, array $query): ResourceObject
    {
        $uri = ($this->queryMerger)($path, $query);
        $json = json_encode($uri->query, JSON_THROW_ON_ERROR);
        $url = sprintf('%s%s', $this->baseUri, $uri->path);

        $curl = sprintf("curl -s -i -H 'Content-Type:application/json' -X %s -d '%s' %s", $method, $json, $url);

        return $this->request($curl, $method, $url);
    }

    /**
     * @param array<string> $output
     */
    public function log(array $output, string $curl): void
    {
        $responseLog = implode(PHP_EOL, $output);
        $log = sprintf("%s\n\n%s", $curl, $responseLog) . PHP_EOL . PHP_EOL;
        file_put_contents($this->logFile, $log, FILE_APPEND);
    }

    public function request(string $curl, string $method, string $url): ResourceObject
    {
        exec($curl, $output);
        $uri = new ResourceUri($url);
        $uri->method = $method;
        $ro = ($this->createResponse)($uri, $output);
        $this->log($output, $curl);

        return $ro;
    }
}
