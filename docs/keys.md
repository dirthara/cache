---
id: keys
title: Keys
sidebar_position: 6
description: Which cache keys Dirthara Cache accepts, and which it refuses.
---

A key is a string of at least one character that does not contain any of the characters PSR-6 and PSR-16 reserve:

```text
{ } ( ) / \ @ :
```

Everything else is accepted, including spaces, dashes, digits, non-ASCII characters, and keys longer than the 64
characters the standards require an implementation to support. Keys are matched exactly, including their letter case.

```php
$pool->getItem('user.42');          // accepted
$pool->getItem('user_42.profile');  // accepted
$pool->getItem('user:42');          // throws InvalidCacheKeyException
$pool->getItem('');                 // throws InvalidCacheKeyException
```

Every operation that takes a key checks it before touching the store, and throws an `InvalidCacheKeyException` when
it is invalid. With several keys, one invalid key fails the whole call, and nothing is read, written, or deleted. A key
that is not a string fails the same way; the [simple cache](simple-cache.md#several-values) accepts integer keys, which
PHP makes of numeric array keys.

`InvalidCacheKeyException` implements both `Psr\Cache\InvalidArgumentException` and
`Psr\SimpleCache\InvalidArgumentException`, so code written against either standard catches it.

:::tip
Use `.` to separate the parts of a key, as in `user.42.profile`. A store for a backend with its own limits on keys,
such as their length, documents those limits.
:::
