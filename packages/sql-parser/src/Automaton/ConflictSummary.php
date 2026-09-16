<?php

declare(strict_types=1);

namespace SqlParser\Automaton;

/**
 * How many conflicts a table build settled by default, against what the grammar expected.
 *
 * @visibility root
 */
final class ConflictSummary
{
    /**
     * @param int $shiftReduce Shift/reduce conflicts the shift won by default
     * @param int $reduceReduce Reduce/reduce conflicts the earlier rule won by default
     * @param int|null $expected Shift/reduce conflicts the grammar declares as expected, if it does
     */
    public function __construct(
        public readonly int $shiftReduce,
        public readonly int $reduceReduce,
        public readonly ?int $expected,
    ) {
    }

    /**
     * Reports whether the build met the grammar's own expectation.
     *
     * A grammar that declares no expectation is met only by a conflict-free
     * build, which is what its generator would demand of it.
     *
     * @return bool True when the shift/reduce count matches and no reduce/reduce conflict remains
     */
    public function isExpected(): bool
    {
        return $this->reduceReduce === 0 && $this->shiftReduce === ($this->expected ?? 0);
    }
}
