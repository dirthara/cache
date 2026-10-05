---
id: intro
title: Dirthara Cache
sidebar_position: 1
description: PSR-6 and PSR-16 caching for PHP and the Dirthara framework.
---

Dirthara Cache implements both PHP caching standards: a PSR-6 cache pool, which works with cache items, and a PSR-16
simple cache, which works with plain keys and values. Code that depends on `Psr\Cache\CacheItemPoolInterface` or
`Psr\SimpleCache\CacheInterface` can use it without knowing it is there.

The package is storage-neutral. A pool keeps its values in a `CacheStore`, which a named driver creates from
configuration; the package ships a store in PHP memory, for tests and for caching within a single PHP process. Stores
for Redis, a database, and other backends are separate packages.

| Piece | Does | Read |
| --- | --- | --- |
| `CachePool` | The PSR-6 pool: items, expiry, and deferred saving | [Cache pool](pool.md) |
| `SimpleCache` | The PSR-16 cache, on top of any PSR-6 pool | [Simple cache](simple-cache.md) |
| `CacheFactory` | Creates a pool from a named driver and its configuration | [Stores and drivers](drivers.md) |
| `CacheDriverRegistry` | Holds the drivers by name | [Stores and drivers](drivers.md) |
| `MemoryCacheDriver` | Creates a store that keeps its values in PHP memory | [Stores and drivers](drivers.md) |
| `NativeCacheSerialiser` | Turns a value into a payload and back | [Serialisation](serialisation.md) |

```php
use Dirthara\Cache\CacheFactory;
use Dirthara\Cache\Config\CacheConfiguration;
use Dirthara\Cache\Driver\CacheDriverRegistry;
use Dirthara\Cache\Driver\Memory\MemoryCacheDriver;
use Dirthara\Cache\Serialiser\NativeCacheSerialiser;
use Dirthara\Cache\SimpleCache;

$drivers = new CacheDriverRegistry();
$drivers->register('memory', new MemoryCacheDriver());

$factory = new CacheFactory($drivers, new NativeCacheSerialiser(), $clock);
$pool = $factory->create(new CacheConfiguration('memory'));

$cache = new SimpleCache($pool);
$cache->set('user.42', $user, 3600);
```

[Getting started](getting-started.md) explains each step, including the `$clock`. See
[installation](installation.md) for the requirements, [keys](keys.md) for which keys are valid, and
[exceptions](exceptions.md) for every failure the package reports.
