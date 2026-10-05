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

Every operation passes on to the pool, so the [keys](keys.md), [expiry](pool.md#expiry), copying, and
[failure handling](pool.md#when-the-store-fails) of the pool apply: a failing store makes `get()` return the default
and makes the writing methods return `false`.

## Time to live

| `$ttl` | The value |
| --- | --- |
| `null` | Never expires. |
| A positive number of seconds, or a `DateInterval` | Expires that long from now. |
| Zero, a negative number, or a negative `DateInterval` | Is deleted rather than stored. |

## Several values

`getMultiple()` and `deleteMultiple()` take the keys from any iterable, and `setMultiple()` takes the keys from the
keys of any iterable, so a generator works as well as an array. When a generator yields a key twice, the last value for
it is stored.

- `getMultiple()` reads every key from the store at once and returns the values in the order the keys were given.
- `setMultiple()` defers every value on the pool and commits once, so the store receives them in one `putMultiple()`.
  Committing also stores anything else that was deferred on the same pool.
- `setMultiple()` returns `false` when a value cannot be serialised, and still stores the others.

PHP turns a numeric string array key such as `'42'` into an integer, so integer keys are accepted and used as the
matching string. Any other key that is not a string throws an `InvalidCacheKeyException`.

:::note
PSR-6 has no way to create an item without looking up its key, so `set()` and `setMultiple()` read the keys from the
store before they write them.
:::

`has()` is meant for warming a cache, not for deciding whether to call `get()`: another process can delete or change
the value between the two calls. Call `get()` with a default instead.
