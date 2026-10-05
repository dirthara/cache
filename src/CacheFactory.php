<?php

declare(strict_types=1);

namespace Dirthara\Cache;

use Dirthara\Cache\Contract\Serialiser;
use Dirthara\Cache\Config\CacheConfiguration;
use Dirthara\Cache\Contract\CacheDriverProvider;
use Dirthara\Cache\Contract\CacheFactory as CacheFactoryContract;

final readonly class CacheFactory implements CacheFactoryContract
{
    public function __construct(
        private CacheDriverProvider $drivers,
        private Serialiser $serialiser,
    ) {}

    public function create(CacheConfiguration $configuration): CachePool
    {
        $store = $this->drivers->driver($configuration->driver)->create($configuration);

        return new CachePool($store, $this->serialiser);
    }
}
