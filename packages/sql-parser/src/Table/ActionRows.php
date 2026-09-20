<?php

declare(strict_types=1);

namespace SqlParser\Table;

/**
 * The explicit actions of every state, one row per state.
 *
 * @visibility root
 */
interface ActionRows
{
    /**
     * Answers the explicit actions of one state.
     *
     * @param int $state State number
     *
     * @return array<int, int> Action code by symbol number
     */
    public function row(int $state): array;

    /**
     * Answers how many states there are.
     *
     * @return int State count
     */
    public function count(): int;
}
