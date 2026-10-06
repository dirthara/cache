<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Fixtures;

use RuntimeException;
use Psr\Cache\CacheException as PsrException;

final class ForeignCacheException extends RuntimeException implements PsrException {}
