<?php

declare(strict_types=1);

namespace Dirthara\Cache\Driver\Memory;

use Dirthara\Cache\Contract\CacheStore;
use Dirthara\Cache\Contract\CacheDriver;
use Dirthara\Cache\Config\CacheConfiguration;

final readonly class MemoryCacheDriver implements CacheDriver
{
    public function create(CacheConfiguration $configuration): CacheStore
    {
        // TODO: Implement create() method.
    }
}
