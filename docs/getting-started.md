---
id: getting-started
title: Getting started
sidebar_position: 3
description: Create a cache pool from a driver, and use it as a PSR-6 pool or a PSR-16 cache.
---

This page wires every piece together: drivers, a serialiser, a clock, a factory, a pool, and a simple cache. Each piece
has its own page with the details.

## 1. Drivers

A driver creates the store a pool keeps its values in. Register each driver the application uses under a name:

```php
use Dirthara\Cache\Driver\CacheDriverRegistry;
use Dirthara\Cache\Driver\Memory\MemoryCacheDriver;

$drivers = new CacheDriverRegistry();
$drivers->register('memory', new MemoryCacheDriver());
```

The memory driver keeps values in the PHP process, which suits tests and caching within one process. Drivers for
shared storage, such as Redis or a database, come from their own packages. See [stores and drivers](drivers.md).

## 2. A clock

The pool reads the current time from a PSR-20 clock to decide when items expire. Any implementation of
`Psr\Clock\ClockInterface` works, such as one the application already has, or this one:

```php
use Psr\Clock\ClockInterface;

final readonly class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
```

Tests can pass a clock that returns a fixed time and move it forward, so they do not have to wait for an item to expire.

## 3. A pool

```php
use Dirthara\Cache\CacheFactory;
use Dirthara\Cache\Config\CacheConfiguration;
use Dirthara\Cache\Serialiser\NativeCacheSerialiser;

$factory = new CacheFactory($drivers, new NativeCacheSerialiser(), new SystemClock());

$pool = $factory->create(new CacheConfiguration('memory'));
```

The configuration names the driver and carries its options. Each call to `create()` asks the driver for a new store.
`NativeCacheSerialiser` uses PHP's own serialisation; read [serialisation](serialisation.md) before using it with a
store that anything else can write to.

## 4. Cache items

The pool implements PSR-6. Ask it for an item, and save the item with a value:

```php
$item = $pool->getItem('user.42');

if ($item->isHit()) {
    $user = $item->get();
} else {
    $user = $repository->find(42);
    $pool->save($item->set($user)->expiresAfter(3600));
}
```

`get()` returns `null` for an item that was not found, even after `set()`, because PSR-6 requires it to; keep the
value you set in a variable of your own.

See [cache pool](pool.md) for expiry, deferred saving, and what happens when the store fails.

## 5. A simple cache

`SimpleCache` implements PSR-16 on top of a pool:

```php
use Dirthara\Cache\SimpleCache;

$cache = new SimpleCache($pool);

$cache->set('user.42', $user, 3600);
$user = $cache->get('user.42');
```

See [simple cache](simple-cache.md).
