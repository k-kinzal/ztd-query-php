<?php

declare(strict_types=1);

namespace SqlParser\Table;

/**
 * Action rows held in memory as they were built.
 *
 * @visibility root
 */
final class ArrayRows implements ActionRows
{
    /**
     * @param list<array<int, int>> $rows Action code by symbol, per state
     */
    public function __construct(private readonly array $rows)
    {
    }

    /**
     * Answers the explicit actions of one state.
     *
     * @param int $state State number
     *
     * @return array<int, int> Action code by symbol number
     */
    public function row(int $state): array
    {
        return $this->rows[$state] ?? [];
    }

    /**
     * Answers how many states there are.
     *
     * @return int State count
     */
    public function count(): int
    {
        return count($this->rows);
    }
}
