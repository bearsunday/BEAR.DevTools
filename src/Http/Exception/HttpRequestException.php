<?php

declare(strict_types=1);

namespace BEAR\Dev\Http\Exception;

use RuntimeException;

/**
 * Thrown when HttpResource could not get any HTTP response (connection refused, timeout, ...)
 */
final class HttpRequestException extends RuntimeException
{
}
