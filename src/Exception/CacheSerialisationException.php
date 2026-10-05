<?php

declare(strict_types=1);

namespace Dirthara\Cache\Exception;

use Throwable;
use RuntimeException;

use function sprintf;

final class CacheSerialisationException extends RuntimeException implements CacheException
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

    public static function unableToSerialise(string $type, ?Throwable $previous = null): self
    {
        return new self(
            message: sprintf('Unable to serialise a value of type "%s" for the cache.', self::printable($type)),
            previous: $previous,
            context: ['type' => self::printable($type)],
        );
    }

    public static function unableToDeserialise(?Throwable $previous = null): self
    {
        return new self(
            message: 'Unable to deserialise a cached payload: the payload is malformed.',
            previous: $previous,
        );
    }

    public static function unknownClass(): self
    {
        return new self(
            message: 'Unable to deserialise a cached payload: it holds an object of a class that does not exist.',
        );
    }
}
