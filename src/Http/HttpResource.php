<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\Http\Exception\HttpRequestException;
use BEAR\Dev\QueryMerger;
use BEAR\Dev\Types;
use BEAR\Resource\RequestInterface;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri as ResourceUri;
use Koriym\PhpServer\PhpServer;
use LogicException;
use Override;

use function array_keys;
use function array_map;
use function bin2hex;
use function chmod;
use function curl_error;
use function curl_exec;
use function curl_init;
use function curl_setopt;
use function debug_backtrace;
use function explode;
use function file_exists;
use function file_put_contents;
use function http_build_query;
use function implode;
use function in_array;
use function is_array;
use function is_file;
use function is_string;
use function json_encode;
use function preg_match;
use function preg_replace;
use function random_bytes;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function sys_get_temp_dir;
use function touch;
use function trim;
use function unlink;

use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_COOKIEFILE;
use const CURLOPT_COOKIEJAR;
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

/** @psalm-import-type MultipartFields from Types */
final class HttpResource implements ResourceInterface
{
    private const CONNECT_TIMEOUT = 10;

    /** A path on this server; anything else would be appended to the host and could name another one */
    private const SERVER_PATH = '#\A/(?!/)#';

    private string $logPath;
    private string $baseUri;

    /** @var array<string, PhpServer> Servers started per host and index, kept alive for the whole process */
    private static array $servers = [];
    private readonly QueryMerger $queryMerger;
    private readonly CreateResponse $createResponse;
    private readonly LinkFollower $linkFollower;
    private readonly MultipartForm $multipartForm;
    private readonly CurlCommand $curlCommand;

    /** @var list<string> The headers sent with every request, as `name: value` */
    private readonly array $headerLines;

    /**
     * The cookies the server sets, sent back on every later request of this instance as a browser does
     *
     * @var non-empty-string
     */
    private readonly string $cookieJar;

    /** @param array<string, string> $headers sent with every request, as name => value */
    public function __construct(string $host, string $index, string $logPath = 'php://stderr', array $headers = [])
    {
        $this->baseUri = sprintf('http://%s', $host);
        $this->logPath = $logPath;

        $this->startServer($host, $index);
        $this->queryMerger = new QueryMerger();
        $this->createResponse = new CreateResponse();
        $this->linkFollower = new LinkFollower($this->queryMerger);
        $this->multipartForm = new MultipartForm();
        $this->curlCommand = new CurlCommand($headers, $this->multipartForm);
        $this->headerLines = array_map(static fn (string $name, string $value): string => $name . ': ' . $value, array_keys($headers), $headers);
        $this->cookieJar = sys_get_temp_dir() . '/bear-dev-http-cookie-' . bin2hex(random_bytes(8));
        // The jar holds the session cookies; cURL keeps the mode of the file it writes them back to.
        touch($this->cookieJar);
        chmod($this->cookieJar, 0600);
    }

    public function __destruct()
    {
        if (! is_file($this->cookieJar)) {
            return;
        }

        unlink($this->cookieJar);
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
        return $this->get($this->linkFollower->href($rel, $ro), $query);
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
        return ($this->linkFollower)('GET', $uri, $query, $this->send(...));
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function post(string $uri, array $query = []): ResourceObject
    {
        return ($this->linkFollower)('POST', $uri, $query, $this->send(...));
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function put(string $uri, array $query = []): ResourceObject
    {
        return ($this->linkFollower)('PUT', $uri, $query, $this->send(...));
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function patch(string $uri, array $query = []): ResourceObject
    {
        return ($this->linkFollower)('PATCH', $uri, $query, $this->send(...));
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function delete(string $uri, array $query = []): ResourceObject
    {
        return ($this->linkFollower)('DELETE', $uri, $query, $this->send(...));
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

    /**
     * @param array<string, mixed> $query
     *
     * @throws HttpRequestException For a path not on this server, or a request other than POST whose query carries a file.
     */
    private function send(string $method, string $path, array $query): ResourceObject
    {
        $uri = ($this->queryMerger)($path, $query);
        if (preg_match(self::SERVER_PATH, $uri->path) !== 1) {
            throw new HttpRequestException(sprintf('"%s" is not a path on %s', $path, $this->baseUri));
        }

        // PHP reads a multipart body into $_FILES for a POST only
        if ($method !== 'POST' && $this->multipartForm->fields($uri->query) !== null) {
            throw new HttpRequestException(sprintf('%s %s carries a file, which only a POST sends', $method, $path));
        }

        if ($method === 'GET') {
            $queryParameter = $uri->query ? '?' . http_build_query($uri->query) : '';
            $url = sprintf('%s%s%s', $this->baseUri, $uri->path, $queryParameter);

            return $this->request($url, 'get', $this->curlCommand->get($url));
        }

        $url = sprintf('%s%s', $this->baseUri, $uri->path);
        $fields = $this->multipartForm->fields($uri->query);
        if ($fields !== null) {
            return $this->request($url, $method, $this->curlCommand->multipart($method, $url, $fields), $fields);
        }

        $json = json_encode($uri->query, JSON_THROW_ON_ERROR);

        return $this->request($url, $method, $this->curlCommand->json($method, $url, $json), $json);
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

    /** @param string|MultipartFields|null $body a JSON body, or multipart/form-data fields */
    public function request(string $url, string $method, string $curlCommand, string|array|null $body = null): ResourceObject
    {
        try {
            $output = $this->executeCurl($url, $method, $body);
        } catch (HttpRequestException $e) {
            $this->log([$e->getMessage()], $curlCommand);

            throw $e;
        }

        // Log the raw output first so it is kept even if it cannot be turned into a response.
        $this->log($output, $curlCommand);
        $uri = new ResourceUri($url);
        $uri->method = $method;

        return ($this->createResponse)($uri, $output);
    }

    /**
     * Execute HTTP request using PHP's native cURL extension
     *
     * @param string|MultipartFields|null $body
     *
     * @return array<string>
     */
    private function executeCurl(string $url, string $method, string|array|null $body = null): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new HttpRequestException(sprintf('Could not initialize cURL for %s', $url)); // @codeCoverageIgnore
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        // Only the connection is time-limited: a slow response (or a server paused in a debugger) is waited for.
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJar);

        $upperMethod = strtoupper($method);
        if ($upperMethod !== 'GET' && $upperMethod !== '') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $upperMethod);
        }

        // An empty "Expect:" stops cURL from waiting for "100 Continue" on large bodies.
        $headers = ['Expect:', ...$this->headerLines];

        if ($body !== null) {
            // cURL sends an array as multipart/form-data with its own Content-Type
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            if (! is_array($body)) {
                $headers[] = 'Content-Type:application/json';
            }
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
