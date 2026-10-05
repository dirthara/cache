---
id: serialisation
title: Serialisation
sidebar_position: 8
description: How Dirthara Cache turns values into payloads, and what the native serialiser trusts.
---

A store keeps string payloads. A `CacheSerialiser` turns a value into a payload when it is saved, and back into the
value when it is read.

## The native serialiser

`NativeCacheSerialiser` uses PHP's `serialize()` and `unserialize()`, so it caches any value PHP can serialise:
scalars, `null`, arrays, enums, and objects with the objects they hold.

| Failure | Thrown as |
| --- | --- |
| A value that cannot be serialised, such as a closure or an anonymous class | `CacheSerialisationException` |
| A payload that is empty, malformed, or followed by extra data | `CacheSerialisationException` |
| A payload holding an object of a class that no longer exists | `CacheSerialisationException` |

A [pool](pool.md#when-the-store-fails) turns each of these into a failed save or a miss. A value cached before a class
was renamed or removed is therefore a miss after a deployment, rather than an unusable `__PHP_Incomplete_Class`. Only
the value itself is checked: an object of a removed class nested inside an array or another object is restored as
`__PHP_Incomplete_Class`. The exception never contains the payload.

:::danger
Only use `NativeCacheSerialiser` with a store that nothing untrusted can write to.

Deserialising lets a payload create an object of any class the application has loaded. That class's
`__unserialize()`, `__wakeup()`, and `__destruct()` methods run while the payload is restored, so anyone who can write
a forged payload to the store can run code in those methods. This is PHP object injection.

The serialiser restores every class on purpose, so that cached objects, such as a `DateTimeImmutable` or a value
object, come back as they were saved. The store is therefore part of the application's trust boundary: protect write
access to a shared cache, such as a Redis server, as you would protect the application's code.
:::

A class that throws an `Error` while it is being restored, such as `DateTimeImmutable` given corrupt data, is not
wrapped in a `CacheSerialisationException`; the `Error` escapes `deserialise()`, and the pool, as it is.

Implement `CacheSerialiser` to use another format, such as JSON for values that are only arrays and scalars. A
serialiser throws an exception that implements `Dirthara\Cache\Exception\CacheException` for a value or payload it
cannot handle, so the pool treats it as a failed save or a miss.
