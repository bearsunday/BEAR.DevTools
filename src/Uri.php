<?php

declare(strict_types=1);

namespace BEAR\Dev;

/** @psalm-immutable */
final class Uri
{
    /** @param  array<string, mixed> $query */
    public function __construct(
        public readonly string $path,
        /** @var array<string, mixed> */
        public readonly array $query,
    ) {
    }
}
