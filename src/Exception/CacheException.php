<?php

declare(strict_types=1);

namespace Dirthara\Cache\Exception;

use Psr\Cache\CacheException as PsrCacheException;
use Psr\SimpleCache\CacheException as PsrSimpleCacheException;

interface CacheException extends PsrCacheException, PsrSimpleCacheException
{
    /**
     * @var array<string, mixed>
     */
    public array $context { get; }

    /**
     * @param array<string, mixed> $context
     */
    public function addContext(array $context): static;
}
