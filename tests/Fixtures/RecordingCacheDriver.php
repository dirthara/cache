<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Fixtures;

use Dirthara\Cache\Contract\CacheStore;
use Dirthara\Cache\Contract\CacheDriver;
use Dirthara\Cache\Config\CacheConfiguration;
use Dirthara\Cache\Driver\Memory\MemoryCacheStore;

final class RecordingCacheDriver implements CacheDriver
{
    /**
     * @var list<CacheConfiguration>
     */
    public private(set) array $configurations = [];

    public function create(CacheConfiguration $configuration): CacheStore
    {
        $this->configurations[] = $configuration;

        return new MemoryCacheStore();
    }
}
