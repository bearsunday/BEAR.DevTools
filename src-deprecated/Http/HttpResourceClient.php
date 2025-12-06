<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Resource\RequestInterface;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use InvalidArgumentException;
use LogicException;
use Ray\Di\InjectorInterface;
use ReflectionClass;

use function array_key_exists;
use function assert;
use function class_exists;
use function dirname;
use function exec;
use function file_put_contents;
use function filter_var;
use function http_build_query;
use function implode;
use function is_array;
use function json_encode;
use function parse_url;
use function sprintf;
use function strrpos;
use function substr;

use const FILE_APPEND;
use const FILTER_VALIDATE_URL;
use const PHP_EOL;
use const PHP_URL_PATH;
use const PHP_URL_QUERY;

/**
 * @deprecated User HttpResource instead
 */
class HttpResourceClient implements ResourceInterface
{
    private readonly ResourceInterface $resource;
    private readonly string $logFile;
    private readonly string $baseUri;

    /** @psalm-param class-string $className */
    public function __construct(string $baseUri, InjectorInterface $injector, string $className)
    {
        static $firstRun = true;

        if (! filter_var($baseUri, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException($baseUri);
        }

        $this->logFile = $this->getLogFile($className);
        if ($firstRun) {
            file_put_contents($this->logFile, '');
            $firstRun = false;
        }

        $this->resource = $injector->getInstance(ResourceInterface::class);
        $this->baseUri = $baseUri;
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

    /** @codeCoverageIgnore */
    public function object(ResourceObject $ro): RequestInterface
    {
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
        $httpUri = $this->getHttpUrl($uri);
        $response = $this->resource->{__FUNCTION__}($httpUri, $query);
        $this->safeLog($uri, $query);

        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function post(string $uri, array $query = []): ResourceObject
    {
        $httpUri = $this->getHttpUrl($uri);
        $response = $this->resource->{__FUNCTION__}($httpUri, $query);
        $this->unsafeLog('POST', $uri, $query);

        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $uri, array $query = []): ResourceObject
    {
        $httpUri = $this->getHttpUrl($uri);
        $response = $this->resource->{__FUNCTION__}($httpUri, $query);
        $this->unsafeLog('PUT', $uri, $query);

        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function patch(string $uri, array $query = []): ResourceObject
    {
        $httpUri = $this->getHttpUrl($uri);
        $response = $this->resource->{__FUNCTION__}($httpUri, $query);
        $this->unsafeLog('PATCH', $uri, $query);

        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $uri, array $query = []): ResourceObject
    {
        $httpUri = $this->getHttpUrl($uri);
        $response = $this->resource->{__FUNCTION__}($httpUri, $query);
        $this->unsafeLog('DELETE', $uri, $query);

        return $response;
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

    public function getLogFile(string $className): string
    {
        $class = substr($className, (int) strrpos($className, '\\') + 1);
        assert(class_exists($className));
        $dir = dirname((string) (new ReflectionClass($className))->getFileName());

        return sprintf('%s/log/%s.log', $dir, $class);
    }

    /** @param array<string> $query */
    private function safeLog(string $uri, array $query): void
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $query += (array) parse_url($uri, PHP_URL_QUERY);
        $queryParameter = $query ? '?' . http_build_query($query) : '';
        $curl = sprintf("curl -s -i '%s%s%s'", $this->baseUri, $path, $queryParameter);
        exec($curl, $output);
        $responseLog = implode(PHP_EOL, $output);
        $log = sprintf("%s\n\n%s", $curl, $responseLog) . PHP_EOL . PHP_EOL;
        file_put_contents($this->logFile, $log, FILE_APPEND);
    }

    /** @param array<string> $query */
    private function unsafeLog(string $method, string $uri, array $query): void
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $query += (array) parse_url($uri, PHP_URL_QUERY);
        $json = json_encode($query);
        $curl = sprintf("curl -s -i -H 'Content-Type:application/json' -X %s -d '%s' %s%s", $method, $json, $this->baseUri, $path);
        exec($curl, $output);
        $responseLog = implode(PHP_EOL, $output);
        $log = sprintf("%s\n\n%s", $curl, $responseLog) . PHP_EOL . PHP_EOL;
        file_put_contents($this->logFile, $log, FILE_APPEND);
    }

    public function getHttpUrl(string $uri): string
    {
        $pUri = parse_url($uri);
        assert(is_array($pUri));
        assert(array_key_exists('path', $pUri));
        if (array_key_exists('scheme', $pUri) && ($pUri['scheme'] === 'http' || $pUri['scheme'] === 'https')) {
            return $uri;
        }

        $query = isset($pUri['query']) ? sprintf('?%s', $pUri['query']) : '';

        return sprintf('%s%s%s', $this->baseUri, $pUri['path'], $query);
    }
}
