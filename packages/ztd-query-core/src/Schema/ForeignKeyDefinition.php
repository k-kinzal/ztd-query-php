<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * One foreign key: which columns point where, and what happens when the parent moves.
 */
final class ForeignKeyDefinition
{
    /**
     * @param non-empty-list<string> $columns
     * @param list<string> $referencedColumns
     */
    public function __construct(
        public readonly array $columns,
        public readonly string $referencedTable,
        public readonly array $referencedColumns,
        public readonly ReferentialAction $onDelete = ReferentialAction::NoAction,
        public readonly ReferentialAction $onUpdate = ReferentialAction::NoAction,
    ) {
    }
}
