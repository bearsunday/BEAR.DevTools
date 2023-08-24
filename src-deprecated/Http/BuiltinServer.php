<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use RuntimeException;
use Symfony\Component\Process\Process;
use function error_log;
use function is_int;
use function register_shutdown_function;
use function sleep;
use function sprintf;
use function strpos;
use function version_compare;
use const PHP_BINARY;
use const PHP_VERSION;

/** @deprecated User koriym/php-server PhpServer instead */
final class BuiltinServer
{
    /**
     * @psalm-var Process
     * @phpstan-var Process<string>
     */
    private $process;

    /** @var string */
    private $host;

    public function __construct(string $host, string $index)
    {
        $this->process = new Process([
            PHP_BINARY,
            '-S',
            $host,
            $index,
        ]);
        $this->host = $host;
        register_shutdown_function(function (): void {
            // @codeCoverageIgnoreStart
            $this->process->stop();
            // @codeCoverageIgnoreEnd
        });
    }

    public function start(): void
    {
        $this->process->start();
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            // @codeCoverageIgnoreStart
            sleep(1);

            return;
            // @codeCoverageIgnoreEnd
        }

        $this->process->waitUntil(function (string $type, string $output): bool {
            if ($type === 'err' && ! is_int(strpos($output, 'started'))) {
                // @codeCoverageIgnoreStart
                error_log($output);
                // @codeCoverageIgnoreEnd
            }

            return (bool) strpos($output, $this->host);
        });
    }

    /** @codeCoverageIgnore */
    public function stop(): void
    {
        $exitCode = $this->process->stop();
        if ($exitCode !== 143) {
            throw new RuntimeException(sprintf('code:%s msg:%s', (string) $exitCode, $this->process->getErrorOutput()));
        }
    }
}
