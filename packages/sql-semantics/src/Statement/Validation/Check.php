<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation;

use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;
use SqlSemantics\Statement\Validation\Failure\InvariantViolation;

/**
 * Always-executed checks; correctness never depends on PHP assertion settings.
 * @visibility SqlSemantics
 */
final class Check
{
    /**
     * Rejects inputs outside the documented construction domain.
     * @phpstan-assert true $condition
     * @throws InvalidConstruction
     */
    public static function input(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new InvalidConstruction($message);
        }
    }

    /**
     * Reports a broken internally derived candidate as an implementation defect.
     * @phpstan-assert true $condition
     * @throws InvariantViolation
     */
    public static function invariant(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new InvariantViolation($message);
        }
    }
}
