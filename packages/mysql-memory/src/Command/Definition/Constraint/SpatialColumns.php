<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

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
}
