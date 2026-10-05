<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Fixtures;

use Dirthara\Cache\Contract\CacheStore;
use Dirthara\Cache\ValueObject\StoredValue;
use Dirthara\Cache\Driver\Memory\MemoryCacheStore;

use function array_map;

final class RecordingCacheStore implements CacheStore
{
    /**
     * @var list<array{string, mixed}>
     */
    public private(set) array $calls = [];

    public bool $failing = false;

    public bool $throwing = false;

    public function __construct(
        public readonly MemoryCacheStore $inner = new MemoryCacheStore(),
    ) {}

    public function get(string $key): ?StoredValue
    {
        $this->record('get', $key);

        return $this->inner->get($key);
    }

    public function getMultiple(array $keys): array
    {
        $this->record('getMultiple', $keys);

        return $this->inner->getMultiple($keys);
    }

    public function put(string $key, StoredValue $value): bool
    {
        $this->record('put', $key);

        return !$this->failing && $this->inner->put($key, $value);
    }

    public function putMultiple(array $values): bool
    {
        $this->record('putMultiple', $values);

        return !$this->failing && $this->inner->putMultiple($values);
    }

    public function delete(string $key): bool
    {
        $this->record('delete', $key);

        return !$this->failing && $this->inner->delete($key);
    }

    public function deleteMultiple(array $keys): bool
    {
        $this->record('deleteMultiple', $keys);

        return !$this->failing && $this->inner->deleteMultiple($keys);
    }

    public function clear(): bool
    {
        $this->record('clear', null);

        return !$this->failing && $this->inner->clear();
    }

    /**
     * @return list<string>
     */
    public function operations(): array
    {
        return array_map(static fn(array $call): string => $call[0], $this->calls);
    }

    private function record(string $operation, mixed $argument): void
    {
        $this->calls[] = [$operation, $argument];

        if ($this->throwing) {
            throw new ContextualException('The store is unavailable.');
        }
    }
}
