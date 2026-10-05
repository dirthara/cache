<?php

declare(strict_types=1);

namespace Dirthara\Cache;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Clock\ClockInterface;
use Psr\Cache\CacheItemInterface;

use const PHP_INT_MAX;

final class CacheItem implements CacheItemInterface
{
    public private(set) mixed $value;

    public private(set) ?DateTimeImmutable $expiry = null;

    private function __construct(
        private readonly string $key,
        private readonly bool $hit,
        mixed $value,
        private readonly ClockInterface $clock,
    ) {
        $this->value = $value;
    }

    public static function hit(string $key, mixed $value, ClockInterface $clock): self
    {
        return new self($key, true, $value, $clock);
    }

    public static function miss(string $key, ClockInterface $clock): self
    {
        return new self($key, false, null, $clock);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->hit ? $this->value : null;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->expiry = $expiration === null ? null : DateTimeImmutable::createFromInterface($expiration);

        return $this;
    }

    public function expiresAfter(DateInterval|int|null $time): static
    {
        $this->expiry = match (true) {
            $time === null => null,
            $time instanceof DateInterval => $this->clock->now()->add($time),
            default => $this->secondsFromNow($time),
        };

        return $this;
    }

    private function secondsFromNow(int $seconds): ?DateTimeImmutable
    {
        $now = $this->clock->now();

        if ($seconds <= 0) {
            return $now;
        }

        if ($seconds > (PHP_INT_MAX - $now->getTimestamp())) {
            return null;
        }

        return $now->setTimestamp($now->getTimestamp() + $seconds)->setMicrosecond((int) $now->format('u'));
    }
}
