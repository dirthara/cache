<?php

declare(strict_types=1);

namespace Dirthara\Cache\Contract;

use Dirthara\Cache\CachePool;
use Dirthara\Cache\Config\CacheConfiguration;

interface CacheFactory
{
    public function create(CacheConfiguration $configuration): CachePool;
}
