<?php

declare(strict_types=1);

namespace Dirthara\Cache\Serialiser;

use Dirthara\Cache\Contract\Serialiser;

class DefaultCacheSerialiser implements Serialiser
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
