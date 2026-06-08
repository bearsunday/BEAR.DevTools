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
use stdClass;

use function curl_exec;
use function curl_init;
use function curl_setopt;
use function escapeshellarg;
use function explode;
use function file_exists;
use function file_put_contents;
use function html_entity_decode;
use function http_build_query;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function preg_match;
use function preg_match_all;
use function preg_split;
use function sprintf;
use function strtolower;
use function strtoupper;
use function trim;

use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HEADER;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const ENT_HTML5;
use const ENT_QUOTES;
use const FILE_APPEND;
use const JSON_THROW_ON_ERROR;
use const PHP_EOL;

final class HttpResource implements ResourceInterface
{
    private string $logFile;
    private string $baseUri;
    private static PhpServer $server;
    private readonly QueryMerger $queryMerger;
    private readonly CreateResponse $createResponse;

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
    public function href(string $rel, array $query = [], ResourceObject|null $ro = null): ResourceObject
    {
        if (! $ro instanceof ResourceObject) {
            throw new LogicException('ResourceObject is required to follow a link.');
        }

        $href = $this->halHref($rel, $ro)
            ?? $this->semanticHtmlHref($rel, $ro->view)
            ?? $this->linkHeaderHref($rel, $ro->headers);
        if ($href === null) {
            throw new LogicException(sprintf('Link rel `%s` is not available.', $rel));
        }

        return $this->get($href, $query);
    }

    /** @param array<string, mixed> $query */
    public function newRequest(object|string $method, string $uri, array $query = []): RequestInterface
    {
        unset($method, $uri, $query);

        throw new LogicException();
    }

    /** @param array<string, mixed> $query */
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

    private function halHref(string $rel, ResourceObject $ro): string|null
    {
        $body = is_array($ro->body) ? $ro->body : [];
        $links = $body['_links'] ?? null;
        if ($links instanceof stdClass) {
            $links = (array) $links;
        }

        if (! is_array($links) || ! isset($links[$rel])) {
            return null;
        }

        $link = $links[$rel];
        if ($link instanceof stdClass) {
            $link = (array) $link;
        }

        if (! is_array($link)) {
            return null;
        }

        $href = $link['href'] ?? null;

        return is_string($href) ? $href : null;
    }

    private function semanticHtmlHref(string $rel, string|null $view): string|null
    {
        if (! is_string($view) || $view === '') {
            return null;
        }

        if (preg_match_all('/<(?:a|area|link)\b(?P<attrs>[^>]*)>/i', $view, $matches) !== 1) {
            return null;
        }

        foreach ($matches['attrs'] as $attrs) {
            if (! $this->hasLinkToken($attrs, $rel)) {
                continue;
            }

            $href = $this->attribute($attrs, 'href');
            if ($href !== null && $href !== '') {
                return $href;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $headers */
    private function linkHeaderHref(string $rel, array $headers): string|null
    {
        $header = $this->headerValue($headers, 'Link');
        if ($header === null) {
            return null;
        }

        $links = preg_split('/,\s*(?=<)/', $header);
        if (! is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (preg_match('/^\s*<([^>]*)>\s*(.*)$/', $link, $match) !== 1) {
                continue;
            }

            $linkRel = $this->linkHeaderParam($match[2], 'rel');
            if ($linkRel === null || ! $this->containsToken($linkRel, $rel)) {
                continue;
            }

            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    private function hasLinkToken(string $attrs, string $rel): bool
    {
        $relAttr = $this->attribute($attrs, 'rel');
        if ($relAttr !== null && $this->containsToken($relAttr, $rel)) {
            return true;
        }

        $classAttr = $this->attribute($attrs, 'class');

        return $classAttr !== null && $this->containsToken($classAttr, $rel);
    }

    private function attribute(string $attrs, string $name): string|null
    {
        if (preg_match('/\b' . $name . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $match) !== 1) {
            return null;
        }

        $value = $match[1] ?? $match[2] ?? $match[3] ?? '';

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
    }

    private function linkHeaderParam(string $attrs, string $name): string|null
    {
        if (preg_match('/(?:^|;)\s*' . $name . '\s*=\s*(?:"([^"]*)"|([^;\s]+))/i', $attrs, $match) !== 1) {
            return null;
        }

        return $match[1] ?? $match[2] ?? null;
    }

    private function containsToken(string $value, string $token): bool
    {
        $tokens = preg_split('/\s+/', trim($value));
        if (! is_array($tokens)) {
            return false;
        }

        return in_array($token, $tokens, true);
    }

    /** @param array<string, mixed> $headers */
    private function headerValue(array $headers, string $name): string|null
    {
        foreach ($headers as $header => $value) {
            if (! is_string($value)) {
                continue;
            }

            if (strtolower($header) === strtolower($name)) {
                return $value;
            }
        }

        return null;
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
