<?php

declare(strict_types=1);

namespace Dirthara\Cache\Serialiser;

use Throwable;
use SplObjectStorage;
use ReflectionReference;
use __PHP_Incomplete_Class;
use Dirthara\Cache\Contract\CacheSerialiser;
use Dirthara\Cache\Exception\CacheSerialisationException;

use function is_array;
use function is_object;
use function serialize;
use function unserialize;
use function get_debug_type;
use function array_key_exists;
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
        } catch (Throwable $exception) {
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
        } catch (Throwable $exception) {
            throw CacheSerialisationException::unableToDeserialise($exception);
        } finally {
            restore_error_handler();
        }

        if ($malformed) {
            throw CacheSerialisationException::unableToDeserialise();
        }

        $references = [];
        /** @var SplObjectStorage<object, null> $objects */
        $objects = new SplObjectStorage();

        if ($this->hasIncompleteClass($restored, $objects, $references)) {
            throw CacheSerialisationException::unknownClass();
        }

        return $restored;
    }

    /**
     * @param SplObjectStorage<object, null> $objects
     * @param array<string, true> $references
     */
    private function hasIncompleteClass(mixed $value, SplObjectStorage $objects, array &$references): bool
    {
        if ($value instanceof __PHP_Incomplete_Class) {
            return true;
        }

        if (is_object($value)) {
            if ($objects->offsetExists($value)) {
                return false;
            }

            $objects->offsetSet($value, null);
            // @mago-expect analysis:invalid-type-cast Mangled property keys allow private values to be inspected without invoking hooks
            $value = (array) $value;
        }

        if (!is_array($value)) {
            return false;
        }

        // @mago-expect analysis:mixed-assignment Cached arrays and object properties can hold any value
        foreach ($value as $key => $nested) {
            $reference = ReflectionReference::fromArrayElement($value, $key);
            if ($reference !== null) {
                $identity = $reference->getId();
                if (array_key_exists($identity, $references)) {
                    continue;
                }
                $references[$identity] = true;
            }

            if ($this->hasIncompleteClass($nested, $objects, $references)) {
                return true;
            }
        }

        return false;
    }
}
