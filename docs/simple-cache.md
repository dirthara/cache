---
id: simple-cache
title: Simple cache
sidebar_position: 5
description: The PSR-16 simple cache, and how it maps onto a PSR-6 pool.
---

`SimpleCache` implements PSR-16's `CacheInterface` on top of any PSR-6 pool:

```php
use Dirthara\Cache\SimpleCache;

$cache = new SimpleCache($pool);

$cache->set('user.42', $user, 3600);

$user = $cache->get('user.42');
$name = $cache->get('user.42.name', 'unknown');
```

| Method | Does |
| --- | --- |
| `get(string $key, mixed $default = null)` | Returns the key's value, or the default when it has none. |
| `set(string $key, mixed $value, DateInterval\|int\|null $ttl = null)` | Stores a value. |
| `delete(string $key)` | Removes the key's value. |
| `clear()` | Removes every value. |
| `getMultiple(iterable $keys, mixed $default = null)` | Returns the values for several keys, keyed by key. |
| `setMultiple(iterable $values, DateInterval\|int\|null $ttl = null)` | Stores several values, keyed by key. |
| `deleteMultiple(iterable $keys)` | Removes several keys' values. |
| `has(string $key)` | Tells whether the key has a value. |

With Dirthara's `CachePool`, every operation passes on to the pool, so the [keys](keys.md), [expiry](pool.md#expiry),
copying, and [failure handling](pool.md#when-the-store-fails) of the pool apply: a failing store makes `get()` return the default
and makes the writing methods return `false`.

With a foreign PSR-6 pool, cache failures may throw. `SimpleCache` translates a foreign `Psr\Cache\CacheException`
into `CachePoolException`, and a foreign `Psr\Cache\InvalidArgumentException` into `InvalidCacheKeyException`, so
callers receive the appropriate PSR-16 exception interface. An exception that already satisfies that interface is
passed through unchanged. Wrappers retain the original as `previous` without copying backend details into their
messages or context. Unrelated programmer errors, such as `TypeError`, still escape.

## Time to live

| `$ttl` | The value |
| --- | --- |
| `null` | Never expires. |
| A positive number of seconds, or a `DateInterval` | Expires that long from now. |
| Zero, a negative number, or a negative `DateInterval` | Is deleted rather than stored. |

With Dirthara's pool, deletion is decided before serialisation. An unserialisable replacement with a zero or
negative TTL still removes the old value. The same rule applies to every target in `setMultiple()`.

## Several values

`getMultiple()` and `deleteMultiple()` take the keys from any iterable, and `setMultiple()` takes the keys from the
keys of any iterable, so a generator works as well as an array. When a generator yields a key twice, the last value for
it is stored.

- With Dirthara's pool, `getMultiple()` reads every key from the store at once and returns one value per unique key in
  requested order. Its iterable preserves string keys such as `'123'`, `'0'`, and `'001'`; converting it to a PHP array
  can change numeric-string keys into integers.
- `setMultiple()` defers every value on the pool and commits once, so the store receives them in one `putMultiple()`.
  Expired values queue deletions instead. Committing also persists anything else deferred on the same pool.
  A failed commit retains the outstanding work on Dirthara's pool for a later `commit()` retry.
- `setMultiple()` returns `false` when a value cannot be serialised, and still stores the others.

PHP turns a numeric string array key such as `'42'` into an integer, so `setMultiple()` accepts integer keys and
uses the matching string. The key lists passed to `getMultiple()` and `deleteMultiple()` must contain strings;
integers and other types throw an `InvalidCacheKeyException`.

:::note
PSR-6 has no way to create an item without looking up its key, so `set()` and `setMultiple()` read the keys from the
store before they write them.
:::

`has()` is meant for warming a cache, not for deciding whether to call `get()`: another process can delete or change
the value between the two calls. Call `get()` with a default instead.
