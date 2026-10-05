---
id: drivers
title: Stores and drivers
sidebar_position: 7
description: The cache store contract, the memory store, drivers, configuration, and creating a pool.
---

## The store contract

A `CacheStore` keeps payloads under keys. The pool does everything else: it checks keys, serialises values, decides
when a value has expired, and handles failures. A store sees only valid keys and `StoredValue` objects, each holding a
string `payload` and the `expiresAt` moment it expires, or `null` when it never does.

| Method | Does |
| --- | --- |
| `get(string $key): ?StoredValue` | Returns the value under the key, or `null` when there is none. |
| `getMultiple(array $keys): array` | Returns the values it has for the keys, keyed by key, leaving out the keys it has none for. |
| `put(string $key, StoredValue $value): bool` | Stores a value under the key, replacing any value already there. |
| `putMultiple(array $values): bool` | Stores several values, keyed by key. |
| `delete(string $key): bool` | Removes the value under the key, succeeding when there is none. |
| `deleteMultiple(array $keys): bool` | Removes the values under several keys. |
| `clear(): bool` | Removes every value. |

A store reports a failure by returning `false`, or by throwing an exception that implements
`Dirthara\Cache\Exception\CacheException`; the pool [handles both](pool.md#when-the-store-fails). A driver package
lets its own exceptions implement that interface as well as its package's own, so its failures are handled.

A store does not have to remove expired values: the pool never returns one, whatever the store hands back. A store for
a backend that can expire keys itself, such as Redis, can pass `expiresAt` on so that expired values do not take up
space.

:::note
PHP turns a numeric string such as `'42'` into an integer when it is an array key, so the arrays that
`putMultiple()` receives and `getMultiple()` returns can have integer keys. The lists of keys the pool passes to a
store always hold strings.
:::

## The memory store

`MemoryCacheStore` keeps its values in a PHP array. It suits tests, and caching within a single process:

- Its values are lost when the process ends, and other processes cannot see them.
- It never removes expired values. They stay in memory until their key is saved again, deleted, or cleared, so a
  long-running process that caches many short-lived keys keeps growing.
- It never fails.

## Drivers

A `CacheDriver` creates a store from a `CacheConfiguration`. A `CacheDriverRegistry` holds drivers by name:

```php
use Dirthara\Cache\Driver\CacheDriverRegistry;
use Dirthara\Cache\Driver\Memory\MemoryCacheDriver;

$drivers = new CacheDriverRegistry();
$drivers->register('memory', new MemoryCacheDriver());

$drivers->has('memory');       // true
$drivers->driver('memory');    // the MemoryCacheDriver
```

Driver names are matched exactly, including their letter case. Registering a second driver under a name throws a
`DuplicateCacheDriverException` and keeps the first; asking for a name without a driver throws a
`CacheDriverNotFoundException`.

The registry implements two contracts, so code can depend on only what it uses: `CacheDriverProvider` for `has()` and
`driver()`, and `CacheDriverRegistry`, which adds `register()`.

`MemoryCacheDriver` ignores the configuration's options and creates a new, separate `MemoryCacheStore` each time.

## Creating a pool

`CacheFactory` creates a [pool](pool.md) on a store from the driver a configuration names:

```php
use Dirthara\Cache\CacheFactory;
use Dirthara\Cache\Config\CacheConfiguration;
use Dirthara\Cache\Serialiser\NativeCacheSerialiser;

$factory = new CacheFactory($drivers, new NativeCacheSerialiser(), $clock);

$pool = $factory->create(new CacheConfiguration('memory'));
```

Each pool gets its own store from the driver, so whether two pools share their values depends on the driver: two
memory pools never do, while pools of a driver for shared storage usually do. A configuration that names a driver that is
not registered throws a `CacheDriverNotFoundException`. Code that only creates pools can depend on the `CacheFactory`
contract in `Dirthara\Cache\Contract`.

## Configuration

A `CacheConfiguration` names a driver and carries the options for it:

```php
$configuration = new CacheConfiguration('redis', [
    'host' => 'localhost',
    'port' => 6379,
    'tls' => false,
]);
```

A driver reads its options with typed accessors:

| Method | Returns |
| --- | --- |
| `string(string $key, ?string $default = null)` | The option, which has to be a string. |
| `int(string $key, ?int $default = null)` | The option, which has to be an integer. |
| `bool(string $key, ?bool $default = null)` | The option, which has to be a boolean. |
| `has(string $key)` | Whether the option is present, even when its value is `null`. |

The default is used only when the option is missing. A missing option without a default, or a present option of
another type, throws an `InvalidCacheConfigurationException`. Values are never converted: the string `'6379'` is not
an integer, and a present `null` is not a missing option. The exception names the option and the type it has, but
never contains its value, because an option can hold a credential.

## Writing a driver

A driver package implements `CacheStore` for its backend and a `CacheDriver` that creates it, reading the options it
needs from the configuration:

```php
use Dirthara\Cache\Config\CacheConfiguration;
use Dirthara\Cache\Contract\CacheDriver;
use Dirthara\Cache\Contract\CacheStore;

final readonly class RedisCacheDriver implements CacheDriver
{
    public function create(CacheConfiguration $configuration): CacheStore
    {
        return new RedisCacheStore(
            host: $configuration->string('host', 'localhost'),
            port: $configuration->int('port', 6379),
        );
    }
}
```
