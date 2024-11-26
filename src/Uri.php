<?php

declare(strict_types=1);

namespace BEAR\Dev;

/** @psalm-immutable */
final class Uri
{
    /** @var string  */
    public $path;

    /** @var array<string, mixed> */
    public $query;

    /** @param  array<string, mixed> $query */
    public function __construct(string $path, array $query)
    {
        $this->path = $path;
        $this->query = $query;
    }
}
