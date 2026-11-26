<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Dev\Http\Exception\HalLinkNotFoundException;
use BEAR\Dev\Http\Exception\HttpRequestException;
use BEAR\Resource\NullResourceObject;
use BEAR\Resource\Uri;
use Koriym\FileUpload\ErrorFileUpload;
use Koriym\FileUpload\FileUpload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

use function array_map;
use function assert;
use function clearstatcache;
use function dirname;
use function escapeshellarg;
use function explode;
use function file_exists;
use function file_get_contents;
use function fileperms;
use function implode;
use function is_string;
use function str_repeat;
use function substr;
use function unlink;

use const PHP_OS_FAMILY;
use const UPLOAD_ERR_NO_FILE;

class HttpResourceTest extends TestCase
{
    private const HAL_HOST = '127.0.0.1:18081';
    private const ECHO_HOST = '127.0.0.1:18483';

    /** @var HttpResource $resource */
    private $resource;

    public function setUp(): void
    {
        $index = dirname(__DIR__) . '/Fake/app/public/index.php';
        assert(file_exists($index));
        $this->resource = new HttpResource('127.0.0.1:8080', $index, __DIR__ . '/log');
    }

    public function testOnGet(): void
    {
        $ro = $this->resource->get('http://127.0.0.1:8080/');
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onGet"', $ro->view);
    }

    public function testOnPost(): void
    {
        $ro = $this->resource->post('/');
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }

    public function testOnPut(): void
    {
        $ro = $this->resource->put('/');
        $this->assertSame(200, $ro->code);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPut"', $ro->view);
    }

    public function testOnPatch(): void
    {
        $ro = $this->resource->patch('/');
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPatch"', $ro->view);
    }

    public function testOnDelete(): void
    {
        $ro = $this->resource->delete('/');
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onDelete"', $ro->view);
    }

    public function testCookieIsSentBack(): void
    {
        $this->assertFalse($this->resource->get('/visit')->body['visited']);
        $this->assertTrue($this->resource->get('/visit')->body['visited']);
    }

    public function testHrefFollowsHalLinkFirst(): void
    {
        $ro = new NullResourceObject();
        $ro->body = [
            '_links' => [
                'goHome' => ['href' => '/'],
            ],
        ];
        $ro->view = '<a href="/other" class="goHome">Other</a>';
        $ro->headers = ['Link' => '</header>; rel="goHome"'];

        $next = $this->resource->href('goHome', [], $ro);

        $this->assertSame(200, $next->code);
        $this->assertStringContainsString('"method": "onGet"', $next->view);
        $this->assertSame('http://127.0.0.1:8080/', (string) $next->uri);
    }

    public function testHrefFollowsSemanticHtmlLinkBeforeHeader(): void
    {
        $ro = new NullResourceObject();
        $ro->body = [];
        $ro->view = '<a href="/" class="goHome">Home</a>';
        $ro->headers = ['Link' => '</header>; rel="goHome"'];

        $next = $this->resource->href('goHome', [], $ro);

        $this->assertSame(200, $next->code);
        $this->assertStringContainsString('"method": "onGet"', $next->view);
        $this->assertSame('http://127.0.0.1:8080/', (string) $next->uri);
    }

    public function testHrefFindsMatchingSemanticHtmlLinkAmongMultipleLinks(): void
    {
        $ro = new NullResourceObject();
        $ro->body = [];
        $ro->view = '<a href="/other" class="other">Other</a><a href="/" class="goHome">Home</a>';
        $ro->headers = ['Link' => '</header>; rel="goHome"'];

        $next = $this->resource->href('goHome', [], $ro);

        $this->assertSame(200, $next->code);
        $this->assertStringContainsString('"method": "onGet"', $next->view);
        $this->assertSame('http://127.0.0.1:8080/', (string) $next->uri);
    }

    public function testHrefFallsBackToLinkHeader(): void
    {
        $ro = new NullResourceObject();
        $ro->body = [];
        $ro->view = '<a href="/other">Other</a>';
        $ro->headers = ['Link' => '</>; rel="goHome"'];

        $next = $this->resource->href('goHome', [], $ro);

        $this->assertSame(200, $next->code);
        $this->assertStringContainsString('"method": "onGet"', $next->view);
        $this->assertSame('http://127.0.0.1:8080/', (string) $next->uri);
    }

    public function testStdErrLog(): void
    {
        $index = dirname(__DIR__) . '/Fake/app/public/index.php';
        $resource = new HttpResource('127.0.0.1:8080', $index);
        $ro = $resource->get('http://127.0.0.1:8080/');
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onGet"', $ro->view);
    }

    public function testHrefFollowsHalLinkWithGet(): void
    {
        $index = __DIR__ . '/index.php';
        assert(file_exists($index));
        $resource = new HttpResource(self::HAL_HOST, $index, __DIR__ . '/log');

        $ro = $resource->get('/');
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"_links"', $ro->view);

        $next = $resource->href('next', [], $ro);
        $this->assertSame(200, $next->code);
        $this->assertStringContainsString('"page": "linked"', $next->view);
        $this->assertFileExists(__DIR__ . '/log/test-href-follows-hal-link-with-get.log');
    }

    public function testHrefThrowsHalLinkNotFoundExceptionForMissingHalLink(): void
    {
        $index = __DIR__ . '/index.php';
        assert(file_exists($index));
        $resource = new HttpResource(self::HAL_HOST, $index, __DIR__ . '/log');
        $ro = $resource->get('/');

        $this->expectException(HalLinkNotFoundException::class);
        $resource->href('missing', [], $ro);
    }

    public function testCreateResponseKeepsSingleLineJsonBody(): void
    {
        $uri = new Uri('http://127.0.0.1:8080/');
        $ro = (new CreateResponse())($uri, [
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            '',
            '{"method":"onGet"}',
        ]);

        $this->assertSame('{"method":"onGet"}', $ro->view);
        $this->assertSame('onGet', $ro->body['method']);
    }

    public function testPostWithSpecialCharactersSingleQuote(): void
    {
        $ro = $this->resource->post('/', ['data' => ['command' => "' AND 1=CONVERT(int, @@version)--"]]);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }

    public function testPostWithSpecialCharactersDoubleQuote(): void
    {
        $ro = $this->resource->post('/', ['data' => ['test' => 'value with "double quotes"']]);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }

    public function testPostWithSpecialCharactersBrackets(): void
    {
        $ro = $this->resource->post('/', ['data' => ['html' => '<script>alert("xss")</script>']]);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }

    public function testPostWithSpecialCharactersParentheses(): void
    {
        $ro = $this->resource->post('/', ['data' => ['sql' => 'SELECT * FROM users WHERE id = (1)']]);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }

    public function testPostWithMixedSpecialCharacters(): void
    {
        $ro = $this->resource->post('/', [
            'data' => ['complex' => 'It\'s a "test" with <brackets> and (parentheses) & special $chars'],
        ]);
        $this->assertSame(200, $ro->code);
        $this->assertStringContainsString('"method": "onPost"', $ro->view);
    }

    public function testLargePostBodyDoesNotSendExpectContinue(): void
    {
        // cURL adds "Expect: 100-continue" for bodies over 1 MiB and then waits for a 100 response.
        $resource = new HttpResource('127.0.0.1:18083', dirname(__DIR__) . '/Fake/expect-echo.php', __DIR__ . '/log');
        $ro = $resource->post('/', ['payload' => str_repeat('x', 1_100_000)]);

        $this->assertSame(200, $ro->code);
        $this->assertNull($ro->body['expect']);
        $this->assertGreaterThan(1_100_000, $ro->body['length']);
        // The logged curl command shows the size instead of a body too long for a command line.
        $log = (string) file_get_contents(__DIR__ . '/log/test-large-post-body-does-not-send-expect-continue.log');
        $this->assertMatchesRegularExpression('/<\d+ bytes omitted>/', $log);
    }

    public function testConnectionFailureThrows(): void
    {
        $log = __DIR__ . '/log/test-connection-failure-throws.log';
        if (file_exists($log)) {
            unlink($log);
        }

        try {
            $this->resource->request('http://127.0.0.1:1/', 'get', 'curl -s -i http://127.0.0.1:1/');
            $this->fail('HttpRequestException should be thrown.');
        } catch (HttpRequestException $e) {
            $this->assertStringContainsString('http://127.0.0.1:1/', $e->getMessage());
            // The cURL error (e.g. "Failed to connect ...") follows the URL.
            $this->assertMatchesRegularExpression('#http://127\.0\.0\.1:1/ failed: \S#', $e->getMessage());
        }

        $this->assertFileExists($log);
        $this->assertStringContainsString('curl -s -i http://127.0.0.1:1/', (string) file_get_contents($log));
    }

    public function testHeadersAreSentWithEveryRequest(): void
    {
        $resource = new HttpResource(self::ECHO_HOST, dirname(__DIR__) . '/Fake/request-echo.php', __DIR__ . '/log', ['X-Story' => 'told']);

        $this->assertSame('told', $resource->get('/')->body['story']);
        $this->assertSame('told', $resource->post('/', ['title' => 'story'])->body['story']);
    }

    public function testQueryWithFileIsSentAsMultipartForm(): void
    {
        $resource = new HttpResource(self::ECHO_HOST, dirname(__DIR__) . '/Fake/request-echo.php', __DIR__ . '/log');
        $ro = $resource->post('/', [
            'title' => 'story',
            'tags' => ['a', 'b'],
            'file' => FileUpload::fromFile(dirname(__DIR__) . '/Fake/upload.txt'),
        ]);

        $this->assertSame('POST', $ro->body['method']);
        $this->assertStringContainsString('"post":{"title":"story","tags":["a","b"]}', (string) $ro->view);
        $this->assertStringContainsString('"files":{"file":{"name":"upload.txt","type":"text\/plain","size":5}}', (string) $ro->view);
    }

    public function testFailedUploadIsNotSent(): void
    {
        $resource = new HttpResource(self::ECHO_HOST, dirname(__DIR__) . '/Fake/request-echo.php', __DIR__ . '/log');
        $failed = new ErrorFileUpload(['name' => 'upload.txt', 'type' => '', 'size' => 0, 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE]);

        $this->expectException(HttpRequestException::class);
        $this->expectExceptionMessage('The upload "file" failed and has no file to send: No file was uploaded');

        $resource->post('/', ['file' => $failed]);
    }

    /** @return array<string, array{string}> */
    public static function notServerPathProvider(): array
    {
        return [
            'a path without a leading slash' => ['0@localhost/echo'],
            'a path that starts with two slashes' => ['http://127.0.0.1:18483//localhost/echo'],
        ];
    }

    #[DataProvider('notServerPathProvider')]
    public function testAPathNotOnTheServerIsNotRequested(string $uri): void
    {
        $resource = new HttpResource(self::ECHO_HOST, dirname(__DIR__) . '/Fake/request-echo.php', __DIR__ . '/log', ['Authorization' => 'Bearer s3cret']);

        $this->expectException(HttpRequestException::class);
        $this->expectExceptionMessage('is not a path on http://127.0.0.1:18483');

        $resource->get($uri);
    }

    public function testSecretHeaderValuesAreHiddenInTheLog(): void
    {
        $resource = new HttpResource(self::ECHO_HOST, dirname(__DIR__) . '/Fake/request-echo.php', __DIR__ . '/log', [
            'Authorization' => 'Bearer s3cret',
            'cookie' => 'sid=c00kie',
            'Api-Key' => 'k3y',
            'x-auth-token' => 't0ken',
            'X-CSRF-Token' => 'csrf',
            'X-Session-Id' => 's1',
            'X-Story' => 'told',
        ]);

        $this->assertSame('Bearer s3cret', $resource->get('/')->body['authorization']);
        [$curlCommand] = explode("\n", (string) file_get_contents(__DIR__ . '/log/test-secret-header-values-are-hidden-in-the-log.log'), 2);
        // escapeshellarg() quotes with ' on POSIX and with " on Windows
        $headers = ['Authorization: ***', 'cookie: ***', 'Api-Key: ***', 'x-auth-token: ***', 'X-CSRF-Token: ***', 'X-Session-Id: ***', 'X-Story: told'];
        $this->assertSame(
            'curl -s -i' . implode('', array_map(static fn (string $header): string => ' -H ' . escapeshellarg($header), $headers)) . ' ' . escapeshellarg('http://127.0.0.1:18483/'),
            $curlCommand,
        );
    }

    public function testMultipartValuesAreLoggedAsFormStrings(): void
    {
        $resource = new HttpResource(self::ECHO_HOST, dirname(__DIR__) . '/Fake/request-echo.php', __DIR__ . '/log');
        $ro = $resource->post('/', [
            'note' => '@/etc/hosts',
            'file' => FileUpload::fromFile(dirname(__DIR__) . '/Fake/upload.txt'),
        ]);

        $this->assertStringContainsString('"post":{"note":"@\/etc\/hosts"}', (string) $ro->view);
        $log = (string) file_get_contents(__DIR__ . '/log/test-multipart-values-are-logged-as-form-strings.log');
        $this->assertStringContainsString(' -F ' . substr(escapeshellarg('file=@'), 0, -1), $log);
        $this->assertStringContainsString(' --form-string ' . escapeshellarg('note=@/etc/hosts') . ' ', $log);
    }

    public function testALongMultipartValueIsOmittedFromTheLog(): void
    {
        $resource = new HttpResource(self::ECHO_HOST, dirname(__DIR__) . '/Fake/request-echo.php', __DIR__ . '/log');
        $note = str_repeat('a', 9000);
        $ro = $resource->post('/', ['note' => $note, 'file' => FileUpload::fromFile(dirname(__DIR__) . '/Fake/upload.txt')]);

        $this->assertStringContainsString($note, (string) $ro->view);
        $log = (string) file_get_contents(__DIR__ . '/log/test-a-long-multipart-value-is-omitted-from-the-log.log');
        $this->assertStringContainsString(' --form-string ' . escapeshellarg('<9005 bytes omitted>') . ' ', $log);
    }

    public function testFilesInAListAreSentAsMultipartForm(): void
    {
        $resource = new HttpResource(self::ECHO_HOST, dirname(__DIR__) . '/Fake/request-echo.php', __DIR__ . '/log', ['Authorization' => 'Bearer s3cret']);
        $upload = FileUpload::fromFile(dirname(__DIR__) . '/Fake/upload.txt');
        $ro = $resource->post('/', ['title' => 'story', 'images' => [$upload, $upload]]);

        $this->assertStringContainsString('"post":{"title":"story"}', (string) $ro->view);
        $this->assertStringContainsString('"files":{"images":{"name":["upload.txt","upload.txt"],"type":["text\/plain","text\/plain"],"size":[5,5]}}', (string) $ro->view);
        $this->assertStringContainsString('"authorization":"Bearer s3cret"', (string) $ro->view);
    }

    public function testGetWithFileThrows(): void
    {
        $resource = new HttpResource(self::ECHO_HOST, dirname(__DIR__) . '/Fake/request-echo.php', __DIR__ . '/log');

        $this->expectException(HttpRequestException::class);
        $this->expectExceptionMessage('GET / carries a file');

        $resource->get('/', ['images' => [FileUpload::fromFile(dirname(__DIR__) . '/Fake/upload.txt')]]);
    }

    public function testAFileGoesWithAPostOnly(): void
    {
        $resource = new HttpResource(self::ECHO_HOST, dirname(__DIR__) . '/Fake/request-echo.php', __DIR__ . '/log');

        $this->expectException(HttpRequestException::class);
        $this->expectExceptionMessage('PUT / carries a file');

        $resource->put('/', ['file' => FileUpload::fromFile(dirname(__DIR__) . '/Fake/upload.txt')]);
    }

    public function testCookieJarIsReadableOnlyByTheOwner(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Windows has no POSIX file modes.');
        }

        $resource = new HttpResource('127.0.0.1:8080', dirname(__DIR__) . '/Fake/app/public/index.php', __DIR__ . '/log');
        $resource->get('/visit');
        $jar = (new ReflectionProperty(HttpResource::class, 'cookieJar'))->getValue($resource);
        assert(is_string($jar));
        clearstatcache();

        $this->assertSame(0600, fileperms($jar) & 0777);
    }
}
