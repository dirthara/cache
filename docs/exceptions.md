---
id: exceptions
title: Exceptions
sidebar_position: 9
description: Every exception Dirthara Cache throws, and when.
---

Every exception the package throws implements `Dirthara\Cache\Exception\CacheException`, so one `catch` handles any
of them. That interface extends both `Psr\Cache\CacheException` and `Psr\SimpleCache\CacheException`. Each exception
also extends the SPL exception that fits it and carries a `context` array with the values that describe the failure.
Context and messages never contain a cached value, a payload, or a configuration value.

```php
use Dirthara\Cache\Exception\CacheException;

try {
    $drivers->register($name, $driver);
} catch (CacheException $exception) {
    $logger->error($exception->getMessage(), $exception->context);
}
```

## Configuration

These are thrown while an application is being set up, and point to a mistake in its code or configuration.

| Exception | Extends | Thrown when |
| --- | --- | --- |
| `DuplicateCacheDriverException` | `InvalidArgumentException` | A second driver is registered under a name. |
| `InvalidCacheConfigurationException` | `InvalidArgumentException` | An option is missing without a default, or has another type than the one read. |
| `CacheDriverNotFoundException` | `RuntimeException` | A pool is created with, or a driver asked for, a name without a driver. |

## Using the cache

| Exception | Extends | Thrown when |
| --- | --- | --- |
| `InvalidCacheKeyException` | `InvalidArgumentException` | A key is invalid, or a foreign PSR-6 pool rejects cache arguments. See [keys](keys.md). |
| `CachePoolException` | `RuntimeException` | A foreign PSR-6 pool throws a cache failure that needs translation to PSR-16. |
| `CacheSerialisationException` | `RuntimeException` | A value cannot be serialised, or a payload cannot be deserialised. See [serialisation](serialisation.md). |

`InvalidCacheKeyException` also implements `Psr\Cache\InvalidArgumentException` and
`Psr\SimpleCache\InvalidArgumentException`. The key it names in its message and context has its control characters
escaped, so a key cannot forge a line in a log.

Dirthara's pool handles `CacheSerialisationException` and other `CacheException` failures from its store or
serialiser as misses or `false`; see [when the store fails](pool.md#when-the-store-fails). Invalid keys throw.
The serialiser throws `CacheSerialisationException` when used on its own, including for nested missing classes and
native restoration failures.

`SimpleCache` can adapt any PSR-6 pool. Foreign PSR-6 cache exceptions are translated to `CachePoolException`, and
foreign invalid-argument exceptions to `InvalidCacheKeyException`. Both preserve the original as `previous` and
expose the appropriate PSR-16 interface. Exceptions already implementing the appropriate PSR-16 interface pass
through unchanged and may be foreign types. Catch `Psr\SimpleCache\CacheException` when adapting an arbitrary pool.
Unrelated programmer errors are not translated.
