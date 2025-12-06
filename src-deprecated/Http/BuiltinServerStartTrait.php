<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use BEAR\Resource\ResourceInterface;
use Ray\Di\InjectorInterface;
use ReflectionClass;

use function dirname;
use function register_shutdown_function;

/**
 * @deprecated User HttpResource instead
 */
trait BuiltinServerStartTrait
{
    private static string $host = '127.0.0.1:8088';

    private string $httpHost = 'http://127.0.0.1:8088';

    private static BuiltinServer $server;

    public static function setUpBeforeClass(): void
    {
        $dir = dirname((string) (new ReflectionClass(static::class))->getFileName());
        self::$server = new BuiltinServer(self::$host, $dir . '/index.php');
        self::$server->start();
        register_shutdown_function(static function (): void {
            self::$server->stop();
        });
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    public function getHttpResourceClient(InjectorInterface $injector, string $class): ResourceInterface
    {
        return new HttpResourceClient($this->httpHost, $injector, $class);
    }
}
