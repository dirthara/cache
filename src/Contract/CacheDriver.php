<?php

declare(strict_types=1);

namespace Dirthara\Cache\Contract;

use Dirthara\Cache\Config\CacheConfiguration;

interface CacheDriver
{
    public function create(CacheConfiguration $configuration): CacheStore;
}
