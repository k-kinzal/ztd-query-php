<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml\Update;

use SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences;
use SqlSemantics\Platform\MySql\Rules\Query\From\InputColumns;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;

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
     * @param list<Assignment|DeferredColumnAssignment> $assignments The requested writes
     * @param list<OrderItem|DeferredOrderItem> $orderBy The ordering clause
     */
    public function problem(?WithClause $with, array $tables, array $assignments, array $orderBy = [], ?Limit $limit = null, Scalar|RowOperand|DeferredOperand|null $where = null): ?Diagnostic
    {
        $sampling = (new \SqlSemantics\Platform\MySql\Rules\Dml\Read\Sampling())->inputs([...$tables, ...$assignments, ...$orderBy, ...($where === null ? [] : [$where])], $with);
        if ($sampling !== null) {
            return $sampling;
        }
        $columns = $this->names($tables) ?? (new InputColumns())->inputs($tables, $with);
        if ($columns !== null) {
            return $columns;
        }
        $target = $this->target($tables, $assignments, $with);
        if ($target !== null) {
            return new WriteMisuse(WriteRule::NonUpdatableTarget, $target->name);
        }
        if (count((new TargetReferences())->occurrences($tables)) > 1 && ($orderBy !== [] || $limit !== null)) {
            return new WriteMisuse($orderBy !== [] ? WriteRule::OrderedMultipleUpdate : WriteRule::LimitedMultipleUpdate);
        }
        $operands = [$where, ...array_map(static fn ($item) => $item->value, $assignments), ...array_map(static fn ($item) => $item->expression, $orderBy)];
        foreach ($operands as $operand) {
            if ($operand instanceof RowOperand || $operand instanceof DeferredOperand) {
                return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$operand]);
            }
        }
        return null;
    }

    /**
     * Rejects exact repeated names in a fixed database namespace.
     *
     * Unqualified physical sources share the current database. Explicitly
     * different databases, and case differences, remain resolution dependencies.
     * Derived and JSON inputs occupy their own database-free namespace.
     *
     * @param list<TableSource> $tables The input relation trees
     */
    public function names(array $tables): ?\SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable
    {
        $seen = [];
        foreach ((new TargetReferences())->occurrences($tables) as $table) {
            $physical = $table instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
            $name = $table instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference ? $table->alias ?? $table->name->name : $table->alias;
            $key = serialize([$physical, $physical ? $table->name->schema?->value : null, $name->value]);
            if (isset($seen[$key])) {
                return new \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable($name);
            }
            $seen[$key] = true;
        }
        return null;
    }

    /**
     * Finds a destination that no catalog can turn into a writable occurrence.
     *
     * @param list<TableSource> $tables The containing update's input namespace
     * @param list<Assignment|DeferredColumnAssignment> $assignments The retained assignment targets
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
