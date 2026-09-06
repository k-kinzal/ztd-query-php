<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

use RuntimeException;

/**
 * Reports a reproducible violation of the SQL syntax contract.
 */
final class SyntaxFailure extends RuntimeException
{
}
