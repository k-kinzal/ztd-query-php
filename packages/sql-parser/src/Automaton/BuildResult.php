<?php

declare(strict_types=1);

namespace SqlParser\Automaton;

use SqlParser\Table\ParseTable;

/**
 * A built parse table together with the conflicts settled while building it.
 *
 * @visibility root
 */
final class BuildResult
{
    /**
     * @param ParseTable $table The table
     * @param ConflictSummary $conflicts What was settled by default
     * @param int $stateCount How many states the automaton has
     */
    public function __construct(
        public readonly ParseTable $table,
        public readonly ConflictSummary $conflicts,
        public readonly int $stateCount,
    ) {
    }
}
