<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Write;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\QueryError;
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
        $resolution = $use->scalarInformation()->resolution;
        if ($resolution instanceof \SqlSemantics\Statement\Reference\Column\MissingColumn) {
            $name = ($use->qualifier?->schema === null ? '' : $use->qualifier->schema->value . '.') . ($use->qualifier === null ? '' : $use->qualifier->name->value . '.') . $use->name->value;
            throw QueryError::BadField->error((new \MySqlMemory\Error\DiagnosticText())->bytes($name, 192), 'field list');
        }
        $position = $resolution instanceof ResolvedColumn
            ? $this->table->definition->resolvedPosition($resolution)
            : $this->table->definition->position($use->name->value);
        if ($position !== null) {
            return $position;
        }

        throw QueryError::BadField->error($use->name->value, 'field list');
    }
}
