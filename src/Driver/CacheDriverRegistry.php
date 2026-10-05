<?php

declare(strict_types=1);

namespace Dirthara\Cache\Driver;

use Dirthara\Cache\Contract\CacheDriver;
use Dirthara\Cache\Contract\CacheDriverRegistry as CacheDriverRegistryContract;

final class CacheDriverRegistry implements CacheDriverRegistryContract
{
    /** @var array<string, CacheDriver> */
    private array $drivers = [];

    public function register(string $name, CacheDriver $driver): void
    {
        // TODO: Implement register() method.
    }
}
