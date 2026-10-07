<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

/**
 * Finds the columns the assignments of an INSERT or UPDATE write.
 *
 * @visibility MySqlMemory
 */
final class Assignments
{
    /**
     * @param Planner $planner The planner of the statement
     * @param StoredTable $table The table written
     */
    public function __construct(public readonly Planner $planner, public readonly StoredTable $table)
    {
    }

    /**
     * Answers the position of the column an assignment writes.
     *
     * @throws \MySqlMemory\Error\SqlError When the column is not a column of the table
     */
    public function position(ColumnUse $use): int
    {
        $resolution = $this->planner->compiler->facts->scalar($use)->resolution;
        $declaration = $resolution instanceof ResolvedColumn ? $resolution->slot->declaration() : null;
        foreach ($this->table->definition->columns as $index => $column) {
            if ($declaration !== null ? $column->declaration === $declaration : strcasecmp($column->name, $use->name->value) === 0) {
                return $index;
            }
        }

        throw ErrorCode::BadField->error($use->name->value, 'field list');
    }
}
