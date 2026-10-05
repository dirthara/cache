<?php

declare(strict_types=1);

namespace Dirthara\Cache\Config;

use Dirthara\Cache\Exception\InvalidCacheConfigurationException;

use function is_int;
use function is_bool;
use function is_string;
use function array_key_exists;

final readonly class CacheConfiguration
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $driver,
        private array $options = [],
    ) {}

    /**
     * @throws InvalidCacheConfigurationException
     */
    public function string(string $key, ?string $default = null): string
    {
        if (!$this->has($key)) {
            return $default ?? throw InvalidCacheConfigurationException::missingOption($this->driver, $key);
        }

        return is_string($this->options[$key])
            ? $this->options[$key]
            : throw InvalidCacheConfigurationException::invalidOptionType(
                $this->driver,
                $key,
                'string',
                $this->options[$key],
            );
    }

    /**
     * @throws InvalidCacheConfigurationException
     */
    public function int(string $key, ?int $default = null): int
    {
        if (!$this->has($key)) {
            return $default ?? throw InvalidCacheConfigurationException::missingOption($this->driver, $key);
        }

        return is_int($this->options[$key])
            ? $this->options[$key]
            : throw InvalidCacheConfigurationException::invalidOptionType(
                $this->driver,
                $key,
                'int',
                $this->options[$key],
            );
    }

    /**
     * @throws InvalidCacheConfigurationException
     */
    public function bool(string $key, ?bool $default = null): bool
    {
        if (!$this->has($key)) {
            return $default ?? throw InvalidCacheConfigurationException::missingOption($this->driver, $key);
        }

        return is_bool($this->options[$key])
            ? $this->options[$key]
            : throw InvalidCacheConfigurationException::invalidOptionType(
                $this->driver,
                $key,
                'bool',
                $this->options[$key],
            );
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }
}
