<?php

declare(strict_types=1);

namespace Dirthara\Cache\Exception;

use Throwable;

interface CacheException extends Throwable
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
