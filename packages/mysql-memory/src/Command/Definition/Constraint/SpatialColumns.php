<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Registry\SpatialCatalog;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;
use SqlSemantics\Statement\Statement;

/**
 * Checks resolved spatial column restrictions against the session's reference systems.
 *
 * Zero is the Cartesian system. Other identifiers must exist, and only the last
 * restriction of a column is looked up. MySQL checks these before index preparation.
 * Verified through SQL on MySQL 8.4.7.
 *
 * @visibility MySqlMemory
 */
final class SpatialColumns
{
    /**
     * Checks spatial reference systems before preparing the indexes of a new table.
     */
    public function prepare(Statement $statement, Session $session): void
    {
        if (!$statement instanceof CreateTable) {
            return;
        }
        $schemaName = $statement->name->schema->value ?? $session->variables->database;
        $name = $statement->name->name->value;
        $schema = $session->instance->dictionary->schema($schemaName);
        $existing = $statement->temporary() ? $session->temporaries->table($schemaName, $name) : ($schema?->table($name) ?? $schema?->views[$name] ?? null);
        if ($existing !== null) {
            return;
        }
        foreach ($statement->elements as $element) {
            if ($element instanceof ColumnDefinition) {
                $this->check($element, $session->instance->registry->spatialCatalog);
            }
        }
    }

    /**
     * Requires the resolved SRID to name an available system.
     */
    public function check(ColumnDefinition $column, SpatialCatalog $catalog): void
    {
        $srid = $column->srid();
        if ($srid !== null && !$column->specification->dataType() instanceof Spatial) {
            throw StatementError::WrongUsage->error('SRID', 'non-geometry column');
        }
        if ($srid !== null && $srid !== 0 && $catalog->name($srid) === null) {
            throw SchemaError::SrsNotFound->error($srid);
        }
    }

    /**
     * Requires copying when an existing column acquires a different SRID restriction.
     * Removing a restriction leaves the bytes and their validation unchanged.
     *
     * @param list<int|null> $origins The old position of each new column
     */
    public function alter(TableDefinition $before, TableDefinition $after, array $origins, ?string $algorithm, ?string $lock): void
    {
        foreach ($after->columns as $position => $column) {
            $origin = $origins[$position] ?? null;
            if ($origin === null || $column->srid === null || $column->srid === $before->columns[$origin]->srid) {
                continue;
            }
            if (in_array($algorithm, ['inplace', 'instant'], true)) {
                throw SchemaError::AlterOperationNotSupported->error('ALGORITHM=' . strtoupper($algorithm), 'ALGORITHM=COPY');
            }
            if ($lock === 'none') {
                throw SchemaError::AlterOperationNotSupportedReason->error('LOCK=NONE', 'COPY algorithm requires a lock', 'LOCK=SHARED');
            }
        }
    }
}
