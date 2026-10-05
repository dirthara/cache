<?php

declare(strict_types=1);

namespace Dirthara\Cache\Serialiser;

use Dirthara\Cache\Contract\CacheSerialiser;

final readonly class NativeCacheSerialiser implements CacheSerialiser
{
    public function serialise(mixed $value): string
    {
        // TODO: Implement serialise() method.
    }

    public function deserialise(string $value): mixed
    {
        // TODO: Implement unserialise() method.
    }
}
