<?php

declare(strict_types=1);

namespace Dirthara\Cache\ValueObject;

use DateTimeImmutable;

final readonly class StoredValue
{
    public function __construct(
        public string $payload,
        public ?DateTimeImmutable $expiresAt,
    ) {}
}
