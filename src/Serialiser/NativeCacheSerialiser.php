<?php

declare(strict_types=1);

namespace Dirthara\Cache\Serialiser;

use Exception;
use __PHP_Incomplete_Class;
use Dirthara\Cache\Contract\CacheSerialiser;
use Dirthara\Cache\Exception\CacheSerialisationException;

use function serialize;
use function unserialize;
use function get_debug_type;
use function set_error_handler;
use function restore_error_handler;

final readonly class NativeCacheSerialiser implements CacheSerialiser
{
    /**
     * @throws CacheSerialisationException
     */
    public function serialise(mixed $value): string
    {
        try {
            return serialize($value);
        } catch (Exception $exception) {
            throw CacheSerialisationException::unableToSerialise(get_debug_type($value), $exception);
        }
    }

    /**
     * @throws CacheSerialisationException
     */
    public function deserialise(string $value): mixed
    {
        if ($value === '') {
            throw CacheSerialisationException::unableToDeserialise();
        }

        $malformed = false;

        set_error_handler(static function () use (&$malformed): bool {
            $malformed = true;

            return true;
        });

        try {
            // @mago-expect analysis:mixed-assignment A cached payload can hold any value
            $restored = unserialize($value, ['allowed_classes' => true]);
        } catch (Exception $exception) {
            throw CacheSerialisationException::unableToDeserialise($exception);
        } finally {
            restore_error_handler();
        }

        if ($malformed) {
            throw CacheSerialisationException::unableToDeserialise();
        }

        if ($restored instanceof __PHP_Incomplete_Class) {
            throw CacheSerialisationException::unknownClass();
        }

        return $restored;
    }
}
