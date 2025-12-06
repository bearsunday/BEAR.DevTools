<?php

declare(strict_types=1);

namespace BEAR\Dev;

/** @psalm-immutable */
final readonly class Uri
{
    /** @param array<string, mixed> $query */
    public function __construct(
        public string $path,
        public array $query,
    ) {
    }
}
