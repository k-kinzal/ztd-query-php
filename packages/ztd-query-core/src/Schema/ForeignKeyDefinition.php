<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

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
