<?php

declare(strict_types=1);

namespace Dirthara\Cache\Serialiser;

use Dirthara\Cache\Contract\CacheSerialiser;

class NativeCacheSerialiser implements CacheSerialiser
{
    public function serialise(mixed $value): string
    {
        // TODO: Implement serialise() method.
    }

    public function unserialise(string $value): mixed
    {
        // TODO: Implement unserialise() method.
    }
}
