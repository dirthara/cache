<?php

declare(strict_types=1);

namespace Dirthara\Cache\Exception;

use Throwable;
use InvalidArgumentException;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

use function sprintf;
use function get_debug_type;

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

    public static function notAString(mixed $key): self
    {
        return new self(
            message: sprintf('Unable to use a cache key of type %s: a key has to be a string.', get_debug_type($key)),
            context: ['type' => get_debug_type($key)],
        );
    }

    public static function empty(): self
    {
        return new self(message: 'Unable to use an empty cache key: a key needs at least one character.');
    }

    public static function reservedCharacters(string $key, string $reserved): self
    {
        return new self(
            message: sprintf(
                'Unable to use the cache key "%s": a key cannot contain any of the reserved characters %s.',
                self::printable($key),
                $reserved,
            ),
            context: ['key' => self::printable($key)],
        );
    }
}
