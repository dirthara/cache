<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Fixtures;

use InvalidArgumentException;
use Psr\Cache\InvalidArgumentException as PsrException;

final class ForeignInvalidArgumentException extends InvalidArgumentException implements PsrException {}
