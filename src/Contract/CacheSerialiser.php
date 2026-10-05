<?php

declare(strict_types=1);

namespace Dirthara\Cache\Contract;

interface CacheSerialiser
{
    public function serialise(mixed $value): string;

    public function unserialise(string $value): mixed;
}
