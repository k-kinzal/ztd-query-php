<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\MaintenanceTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Derives the tables and columns VACUUM and ANALYZE name.
 *
 * Rule: PG-MAINTENANCE-TARGET-001. Each table name resolves by
 * PG-TABLE-TARGET-001. A listed column is looked up among the columns of a
 * declared table whose column list is complete, system columns excluded; a
 * column it does not have is the diagnostic the server raises. With an undeclared table or an
 * incomplete column list nothing is claimed about the columns: the fact of
 * the table carries the missing declaration. Termination: one pass over the
 * tables and their columns.
 * Source: https://www.postgresql.org/docs/17/sql-analyze.html, https://www.postgresql.org/docs/17/sql-vacuum.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class MaintenanceFacts
{
    /**
     * Records the facts of each table and tells whether any table lists columns.
     *
     * @param list<MaintenanceTarget> $targets
     */
    public function targets(Derivation $derivation, array $targets): bool
    {
        $columns = false;
        foreach ($targets as $target) {
            $fact = $derivation->target($target, (new Targets())->resolve($derivation, $target->table));
            $columns = $columns || $target->columns !== [];
            if (!$fact->table instanceof DeclaredTable || !$fact->table->table->complete) {
                continue;
            }
            $declared = [];
            foreach ($fact->table->table->columns as $column) {
                $declared[] = $column->name->value;
            }
            foreach ($target->columns as $column) {
                if (!$this->has($derivation, $declared, $column->value)) {
                    $derivation->report(new UtilityProblem(UtilityProblemKind::MissingColumn, [$column->value, $fact->table->table->name->name->value]));
                }
            }
        }

        return $columns;
    }

    /**
     * Tells whether a name is among declared column names under the name comparison of the context.
     *
     * @param list<string> $declared
     */
    public function has(Derivation $derivation, array $declared, string $name): bool
    {
        foreach ($declared as $candidate) {
            if ($derivation->context->columnNames->equal($candidate, $name)) {
                return true;
            }
        }

        return false;
    }
}
