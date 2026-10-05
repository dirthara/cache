---
id: installation
title: Installation
sidebar_position: 2
description: Requirements and installation status for Dirthara Cache.
---

## Requirements

PHP 8.5 or later within the PHP 8 series is required. Composer installs its two
runtime dependencies, the PSR interface packages it implements:

| Package | Provides |
| --- | --- |
| `psr/cache` `^3.0` | The PSR-6 caching interfaces. |
| `psr/simple-cache` `^3.0` | The PSR-16 simple cache interfaces. |

The package declares that it provides `psr/cache-implementation` and
`psr/simple-cache-implementation`, so a library that requires either can be
satisfied by installing Dirthara Cache.

## Package installation

Once published, install the package using Composer:

```sh
composer require dirthara/cache
```

:::caution
There is no published release yet. The command above describes the intended
installation after publication.
:::

For development, follow the Docker and Composer setup in the repository's
[README](https://github.com/dirthara/cache#readme). Development tooling
includes PHPUnit, Mago, and Xdebug.
