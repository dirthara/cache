<?php

declare(strict_types=1);

namespace Dirthara\Cache;

use DateInterval;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

class CacheItem implements CacheItemInterface
{
    public function getKey(): string
    {
        // TODO: Implement getKey() method.
    }

    public function get(): mixed
    {
        // TODO: Implement get() method.
    }

    public function isHit(): bool
    {
        // TODO: Implement isHit() method.
    }

    public function set(mixed $value): static
    {
        // TODO: Implement set() method.
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        // TODO: Implement expiresAt() method.
    }

    public function expiresAfter(DateInterval|int|null $time): static
    {
        // TODO: Implement expiresAfter() method.
    }
}
