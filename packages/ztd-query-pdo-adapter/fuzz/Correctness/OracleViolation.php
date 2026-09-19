<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use RuntimeException;

/**
 * An observed difference that must fail a fuzz execution.
 */
final class OracleViolation extends RuntimeException
{
}
