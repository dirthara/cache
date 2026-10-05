<?php

declare(strict_types=1);

namespace Dirthara\Cache\Exception;

use Throwable;
use InvalidArgumentException;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

final class InvalidCacheKeyException extends InvalidArgumentException implements
    CacheException,
    CacheInvalidArgumentException,
    SimpleCacheInvalidArgumentException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
    }
}
