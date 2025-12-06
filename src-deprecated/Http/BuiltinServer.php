<?php

declare(strict_types=1);

namespace BEAR\Dev\Http;

use RuntimeException;
use Symfony\Component\Process\Process;

use function register_shutdown_function;
use function sprintf;
use function str_contains;

use const PHP_BINARY;

/** @deprecated User koriym/php-server PhpServer instead */
final class BuiltinServer
{
    /**
     * @psalm-var Process
     * @phpstan-var Process<string>
     */
    private readonly Process $process;

    public function __construct(
        private readonly string $host,
        string $index,
    ) {
        $this->process = new Process([
            PHP_BINARY,
            '-S',
            $host,
            $index,
        ]);
        register_shutdown_function(function (): void {
            // @codeCoverageIgnoreStart
            $this->process->stop();
            // @codeCoverageIgnoreEnd
        });
    }

    public function start(): void
    {
        $this->process->start();
        $this->process->waitUntil(function (string $type, string $output): bool {
            if ($type === 'err' && ! str_contains($output, 'started')) {
                // @codeCoverageIgnoreStart
                error_log($output);
                // @codeCoverageIgnoreEnd
            }

            return str_contains($output, $this->host);
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
