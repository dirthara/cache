---
id: pool
title: Cache pool
sidebar_position: 4
description: The PSR-6 cache pool, its items, expiry, deferred saving, and how it handles a failing store.
---

`CachePool` implements PSR-6's `CacheItemPoolInterface`. It keeps its values in a [store](drivers.md), turns them
into payloads with a [serialiser](serialisation.md), and reads the current time from a PSR-20 clock. A
[factory](drivers.md#creating-a-pool) usually creates it, but it can be constructed directly:

```php
use Dirthara\Cache\CachePool;
use Dirthara\Cache\Driver\Memory\MemoryCacheStore;
use Dirthara\Cache\Serialiser\NativeCacheSerialiser;

$pool = new CachePool(new MemoryCacheStore(), new NativeCacheSerialiser(), $clock);
```

## Items

| Method | Does |
| --- | --- |
| `getItem(string $key)` | Returns the item for the key, found or not. |
| `getItems(array $keys = [])` | Returns the items for several keys, read from the store at once. |
| `hasItem(string $key)` | Tells whether the key has a value that has not expired. |
| `save(CacheItemInterface $item)` | Stores the item's value now. |
| `deleteItem(string $key)` | Removes the key's value. |
| `deleteItems(array $keys)` | Removes several keys' values at once. |
| `clear()` | Removes every value in the store. |

An item is a `CacheItem`. `isHit()` tells whether its key had a value, `get()` returns that value, and `set()` gives it
a new one to save:

```php
$item = $pool->getItem('user.42');

if ($item->isHit()) {
    $user = $item->get();
} else {
    $user = $repository->find(42);
    $pool->save($item->set($user)->expiresAfter(3600));
}
```

:::caution
`get()` returns `null` for an item that was not found, even after `set()`. PSR-6 requires this. The value an item will
be saved with is available as its `value` property.
:::

`null` is a value like any other: an item found with a cached `null` is a hit, and `get()` returns `null`. Use
`isHit()` to tell the two apart.

Every value is stored as a serialised copy, so changing an object after saving it does not change the cached value,
and each `get()` from a new item returns a new copy.

`getItems()` returns the items keyed by their keys, in the order they were asked for, with a key asked for twice
returned once. PHP turns a numeric string such as `'42'` into an integer when it is an array key, so the item for
`'42'` is under the key `42`. `hasItem()` reads and deserialises the value, the same as `getItem()`.

`deleteItem()` and `deleteItems()` succeed for a key without a value.

The pool only saves items it created. `save()` and `saveDeferred()` return `false` for any other implementation of
`CacheItemInterface`.

## Expiry

An item without an expiry is kept until it is deleted or the store is cleared. Give it one with:

| Method | Expires the item |
| --- | --- |
| `expiresAfter(int $seconds)` | The given number of seconds from now. |
| `expiresAfter(DateInterval $interval)` | The given interval from now. |
| `expiresAt(DateTimeInterface $moment)` | At the given moment. |
| `expiresAfter(null)` or `expiresAt(null)` | Never, removing an expiry set before. |

"Now" is the time of the clock when `expiresAfter()` is called, not when the item is saved. Zero or a negative number
of seconds expires the item at once, and a number of seconds too large for PHP's dates means the item never expires.

An item is expired from the moment its expiry is reached. The pool then treats its value as not found, and saving an
item whose expiry has already passed deletes the key's value instead of storing it.

:::note
An item that was found does not carry the expiry it was stored with. Saving it again without setting an expiry stores
it without one, so set the expiry each time you save.
:::

An expired value is not removed from the store when it is read: it stays there until the key is saved again, deleted,
or cleared, unless the store removes it itself. The [memory store](drivers.md#the-memory-store) never does.

## Deferred saving

`saveDeferred()` queues an item, and `commit()` stores every queued item at once, with a single `putMultiple()` on the
store:

```php
foreach ($users as $user) {
    $pool->saveDeferred($pool->getItem('user.' . $user->id)->set($user));
}

$pool->commit();
```

- The item is serialised when it is deferred. Changing it or the objects in its value afterwards does not change what
  is committed, and `saveDeferred()` returns `false` for a value that cannot be serialised.
- Until it is committed, `getItem()`, `getItems()`, and `hasItem()` return the deferred value without reading the
  store.
- A deferred item whose expiry has passed by the time it is committed is deleted from the store instead.
- `save()`, `deleteItem()`, `deleteItems()`, and `clear()` drop the deferred items for the keys they affect.
- `commit()` returns `false` when the store fails to store or delete any of them. The deferred items are forgotten
  either way; commit them again to retry.
- The pool commits the items still deferred when it is destroyed, so they are not lost when the application forgets
  to commit them.

## When the store fails

Caching should not break an application, so the pool does not let a failing store or serialiser escape. A store
reports a failure by returning `false` or by throwing an exception that implements
`Dirthara\Cache\Exception\CacheException`, and the pool turns it into:

| Operation | Becomes |
| --- | --- |
| Reading a value, or deserialising it | A miss, as if the key had no value. |
| Saving, deleting, clearing, or committing | `false`. |
| Serialising a value | `false` from `save()` or `saveDeferred()`, keeping the value already cached. |

An [invalid key](keys.md) is a mistake in the calling code rather than a failure of the cache, so it always throws an
`InvalidCacheKeyException`, before the store is touched. Any other exception or `Error`, such as a `TypeError`, is a
bug and escapes as it is.
