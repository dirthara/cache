---
id: installation
title: Installation
sidebar_position: 2
description: Requirements and installation of Dirthara Cache.
---

## Requirements

PHP 8.5 or later within the PHP 8 series is required. Composer installs its three
runtime dependencies, the PSR interface packages it implements or uses:

| Package | Provides |
| --- | --- |
| `psr/cache` `^3.0` | The PSR-6 caching interfaces. |
| `psr/clock` `^1.0` | The PSR-20 clock interface, which the cache reads the current time from. |
| `psr/simple-cache` `^3.0` | The PSR-16 simple cache interfaces. |

The package declares that it provides `psr/cache-implementation` and
`psr/simple-cache-implementation`, so a library that requires either can be
satisfied by installing Dirthara Cache.

:::note
`psr/clock` holds only the interface. The cache needs an implementation of
`Psr\Clock\ClockInterface` to read the current time from, which this package does
not ship; see [getting started](getting-started.md#2-a-clock).
:::

## Package installation

Install the package with Composer:

```sh
composer require dirthara/cache
```

For development, follow the Docker and Composer setup in the repository's
[README](https://github.com/dirthara/cache#readme). Development tooling
includes PHPUnit 13, Mago, and Xdebug. A separate locked Composer environment
uses PHPUnit 12 for external PSR compliance tests; it does not change the
package's runtime dependencies.
