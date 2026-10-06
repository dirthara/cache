<?php

declare(strict_types=1);

namespace Dirthara\Cache\Exception;

use Throwable;
use RuntimeException;

final class CachePoolException extends RuntimeException implements CacheException
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

    public static function operationFailed(Throwable $previous): self
    {
        return new self(message: 'Unable to complete an operation on the cache pool.', previous: $previous);
    }
}
