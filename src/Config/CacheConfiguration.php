<?php

declare(strict_types=1);

namespace Dirthara\Cache\Config;

final readonly class CacheConfiguration
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $driver,
        private array $options = [],
    ) {}

    public function string(string $key, ?string $default = null): string {}

    public function int(string $key, ?int $default = null): int {}

    public function bool(string $key, ?bool $default = null): bool {}

    public function has(string $key): bool {}
}
