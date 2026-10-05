<?php

declare(strict_types=1);

namespace Dirthara\Cache\Contract;

interface CacheDriverProvider
{
    public function has(string $name): bool;

    public function driver(string $name): CacheDriver;
}
