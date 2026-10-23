<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Math;

/**
 * An argument of GREATEST or LEAST as it compares: what orders it, and what the result writes of it.
 *
 * @visibility MySqlMemory
 */
final class Rank
{
    /**
     * @param int|float|string $order What orders the argument: the bits of an integer, a decimal text, a double, a text in the collation of the result, or a sortable moment
     * @param int|float|string $value The value of the argument
     * @param bool $unsigned Whether the bits of an integer are unsigned
     * @param list<int>|null $parts The year, month, day, hour, minute, second and microsecond of a moment, or null for a value that is no date
     */
    public function __construct(
        public readonly int|float|string $order,
        public readonly int|float|string $value,
        public readonly bool $unsigned = false,
        public readonly ?array $parts = [],
    ) {
    }

    /**
     * Tells whether the argument was read as a date and is none.
     */
    public function dateless(): bool
    {
        return $this->parts === null;
    }
}
