<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml\Update;

use SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences;
use SqlSemantics\Platform\MySql\Rules\Query\From\InputColumns;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Identifies UPDATE contradictions independent of catalog and session state.
 *
 * A derived, JSON or local common table cannot be an update destination.
 * Joined input can still supply values while assignments target physical tables.
 * Unknown columns and ambiguous names remain resolution dependencies. Stored
 * instructions retain these complete operands until invocation.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Structure
{
    /**
     * @param list<TableSource> $tables The input occurrences
     * @param list<Assignment> $assignments The requested writes
     * @param list<OrderItem> $orderBy The ordering clause
     */
    public function problem(?WithClause $with, array $tables, array $assignments, array $orderBy = [], ?Limit $limit = null): ?Diagnostic
    {
        $columns = (new InputColumns())->inputs($tables, $with);
        if ($columns !== null) {
            return $columns;
        }
        $target = $this->target($tables, $assignments, $with);
        if ($target !== null) {
            return new WriteMisuse(WriteRule::NonUpdatableTarget, $target->name);
        }
        if (count((new TargetReferences())->occurrences($tables)) > 1) {
            return $orderBy !== [] ? new WriteMisuse(WriteRule::OrderedMultipleUpdate) : ($limit === null ? null : new WriteMisuse(WriteRule::LimitedMultipleUpdate));
        }
        return null;
    }

    /**
     * Finds a destination that no catalog can turn into a writable occurrence.
     *
     * @param list<TableSource> $tables The containing update's input namespace
     * @param list<Assignment> $assignments The retained assignment targets
     */
    public function target(array $tables, array $assignments, ?WithClause $with = null): ?QualifiedName
    {
        $references = new TargetReferences();
        $names = $references->names($tables);
        if ($names !== [] && array_filter($names, static fn (QualifiedName $name): bool => $references->readOnly([$name], $tables, $with) === null) === []) {
            return $names[0];
        }
        foreach ($assignments as $assignment) {
            $qualifier = $assignment->column->qualifier;
            if ($qualifier !== null && $references->readOnly([$qualifier], $tables, $with) !== null) {
                return $qualifier;
            }
        }
        return null;
    }
}
