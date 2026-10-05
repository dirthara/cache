<?php

declare(strict_types=1);

namespace Dirthara\Cache\Contract;

interface CacheDriverRegistry extends CacheDriverProvider
{
    public function register(string $name, CacheDriver $driver): void;
}
