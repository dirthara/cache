<?php

declare(strict_types=1);

namespace Dirthara\Cache\Driver;

use Dirthara\Cache\Contract\CacheDriver;
use Dirthara\Cache\Exception\CacheDriverNotFoundException;
use Dirthara\Cache\Exception\DuplicateCacheDriverException;
use Dirthara\Cache\Contract\CacheDriverRegistry as CacheDriverRegistryContract;

use function array_key_exists;

final class CacheDriverRegistry implements CacheDriverRegistryContract
{
    /** @var array<string, CacheDriver> */
    private array $drivers = [];

    /**
     * @throws DuplicateCacheDriverException
     */
    public function register(string $name, CacheDriver $driver): void
    {
        if (array_key_exists($name, $this->drivers)) {
            throw DuplicateCacheDriverException::alreadyRegistered($name);
        }

        $this->drivers[$name] = $driver;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->drivers);
    }

    /**
     * @throws CacheDriverNotFoundException
     */
    public function driver(string $name): CacheDriver
    {
        return $this->drivers[$name] ?? throw CacheDriverNotFoundException::for($name);
    }
}
