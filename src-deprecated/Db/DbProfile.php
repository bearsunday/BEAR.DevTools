<?php

declare(strict_types=1);

namespace BEAR\Dev\Db;

use Aura\Sql\ExtendedPdoInterface;
use Aura\Sql\Profiler\Profiler;
use Psr\Log\LoggerInterface;
use Ray\Di\InjectorInterface;

/**
 * Deprecated. Install [AuraSqlProfileModule](https://github.com/ray-di/Ray.AuraSqlModule#profile) instead.
 *
 * @deprecated
 */
final class DbProfile
{
    public function __construct(InjectorInterface $injector)
    {
        $pdo = $injector->getInstance(ExtendedPdoInterface::class);
        $logger = $injector->getInstance(LoggerInterface::class);
        $profiler = new Profiler($logger);
        $profiler->setActive(true);
        $pdo->setProfiler($profiler);
    }

    /**
     * @return array<string>
     *
     * @deprecated
     */
    public function log(): array
    {
        return [];
    }
}
