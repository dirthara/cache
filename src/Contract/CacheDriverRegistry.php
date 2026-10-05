<?php

declare(strict_types=1);

namespace Dirthara\Cache\Contract;

interface CacheDriverRegistry
{
    public function register(string $name, CacheDriver $driver): void;
}
