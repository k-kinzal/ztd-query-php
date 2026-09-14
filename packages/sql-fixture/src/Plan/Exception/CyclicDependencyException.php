<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Exception;

use SqlFixture\Plan\PlanStructureException;

/**
 * The pending table dependencies contain a cycle.
 */
final class CyclicDependencyException extends PlanStructureException
{
    /**
     * The pending table dependencies contain a cycle.
     * @param non-empty-list<string> $blockedTables
     */
    public function __construct(
        public readonly array $blockedTables,
    ) {
        parent::__construct(sprintf(
            'The plan contains cyclic dependencies among: %s. No generation order satisfies them.',
            implode(', ', $blockedTables)
        ));
    }
}
