<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

use RuntimeException;

/**
 * Aborts verification when the fixed database environment is unavailable.
 */
final class InfrastructureFailure extends RuntimeException
{
}
