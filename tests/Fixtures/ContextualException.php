<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Fixtures;

use RuntimeException;
use Dirthara\Cache\Exception\CacheException;
use Dirthara\Cache\Exception\HasExceptionContext;

final class ContextualException extends RuntimeException implements CacheException
{
    use HasExceptionContext;

    public static function describe(string $value): string
    {
        return self::printable($value);
    }
}
