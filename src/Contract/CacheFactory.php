<?php

declare(strict_types=1);

namespace Dirthara\Cache\Contract;

use Psr\Cache\CacheItemPoolInterface;
use Dirthara\Cache\Config\CacheConfiguration;

interface CacheFactory
{
    public function create(CacheConfiguration $configuration): CacheItemPoolInterface;
}
