<?php

declare(strict_types=1);

namespace BEAR\Dev;

use CURLFile;

/**
 * Type definitions for BEAR.DevTools
 *
 * @phpcs:disable SlevomatCodingStandard.Commenting.DocCommentSpacing
 *
 * @psalm-type MultipartFields = array<string, string|CURLFile>
 */
final class Types
{
    /**
     * @codeCoverageIgnore
     * @psalm-pure
     */
    private function __construct()
    {
    }
}
