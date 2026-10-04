<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\Composite;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\DefaultRequest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\AllFields;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\Assignment;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\ColumnTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\RowAssignment;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\TargetTable;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Derives the columns an INSERT column list or a SET list writes, and the values assigned to them.
 *
 * Rule: PG-ASSIGNMENT-001. A written name is a column of the target
 * table, never a qualifier; a name the table certainly lacks is reported, a
 * system column cannot be written, and a step `.*` is reported. A column
 * written whole may not be written again, whole or in part, in the same
 * list; parts of one column (different subscripts or fields) may. The
 * subscripts of the steps see the environment of the values. A value is
 * derived in the environment of the clause and must convert to the column
 * type (PG-ASSIGNMENT-CAST-001) when the whole column is written; DEFAULT
 * takes the column default. A multiple-column item takes its values from a
 * row constructor, whose fields may be DEFAULT, or from a scalar subquery,
 * with as many values as columns; any other source is reported.
 * Termination: one pass over the items.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html, https://www.postgresql.org/docs/17/sql-update.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Assignments
{
    /**
     * Derives a list of written columns and answers, per column, the slot written whole, or null when only a part is written or the column is not known.
     *
     * @param list<ColumnTarget> $columns
     *
     * @return list<OutputSlot|null>
     */
    public function columns(array $columns, VisibleRelation $target, Derivation $derivation, Environment $environment, ManipulationMisuseRule $repeated): array
    {
        $names = $derivation->context->columnNames;
        $whole = [];
        $partial = [];
        $slots = [];
        foreach ($columns as $column) {
            $column->deriveClause($derivation, $environment);
            foreach ($column->steps as $step) {
                if ($step instanceof AllFields) {
                    $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::RowExpansion));
                }
            }
            $key = $names->fold($column->column->value);
            if (isset($whole[$key]) || ($column->steps === [] && isset($partial[$key]))) {
                $derivation->report(new ManipulationMisuse($repeated, $column->column->value));
            }
            if ($column->steps === []) {
                $whole[$key] = true;
            } else {
                $partial[$key] = true;
            }
            $slot = $this->slot($column, $target, $derivation);
            $slots[] = $column->steps === [] ? $slot : null;
        }

        return $slots;
    }

    /**
     * Finds the slot of the target a column name writes, reporting a name the target certainly lacks and a system column.
     */
    public function slot(ColumnTarget $column, VisibleRelation $target, Derivation $derivation): ?OutputSlot
    {
        $names = $derivation->context->columnNames;
        foreach ($target->shape->slots as $slot) {
            if ($slot->name !== null && $names->equal($slot->name->value, $column->column->value)) {
                return $slot;
            }
        }
        foreach ($target->implicit as $implicit) {
            foreach ($implicit->names as $name) {
                if ($names->equal($name->value, $column->column->value)) {
                    $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::SystemColumnAssignment, $column->column->value));

                    return null;
                }
            }
        }
        if ($target->shape->complete() && $target->shape->slots !== [] && $target->relation instanceof TargetTable) {
            $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::UnknownTargetColumn, $column->column->value, $target->relation->table->name->name->value));
        }

        return null;
    }

    /**
     * Derives the items of a SET list.
     *
     * @param list<Assignment|RowAssignment> $assignments
     */
    public function assign(array $assignments, VisibleRelation $target, Derivation $derivation, Environment $environment): void
    {
        $columns = [];
        foreach ($assignments as $assignment) {
            array_push($columns, ...($assignment instanceof Assignment ? [$assignment->column] : $assignment->columns));
        }
        $slots = $this->columns($columns, $target, $derivation, $environment, ManipulationMisuseRule::RepeatedAssignment);
        $position = 0;
        foreach ($assignments as $assignment) {
            if ($assignment instanceof Assignment) {
                $this->value($slots[$position], $assignment->value, $derivation->scalar($assignment->value, $environment)->type, $derivation);
                $position++;
                continue;
            }
            $this->row($assignment, array_slice($slots, $position, count($assignment->columns)), $derivation, $environment);
            $position += count($assignment->columns);
        }
    }

    /**
     * Derives the source of a multiple-column item and checks it against its columns.
     *
     * @param list<OutputSlot|null> $slots
     */
    public function row(RowAssignment $assignment, array $slots, Derivation $derivation, Environment $environment): void
    {
        $fact = $derivation->scalar($assignment->source, $environment);
        if (!$assignment->source instanceof RowConstructor && !$assignment->source instanceof ScalarSubquery) {
            $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::MultipleAssignmentSource));

            return;
        }
        $types = $this->types($assignment->source, $fact->type);
        if ($types === null) {
            return;
        }
        $ambiguous = $assignment->source instanceof ScalarSubquery && count($slots) === 1;
        if ($ambiguous && count($types) !== 1) {
            return;
        }
        if (count($types) !== count($slots)) {
            $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::MultipleAssignmentArity));

            return;
        }
        foreach ($types as $position => $type) {
            if ($type !== null) {
                $this->value($slots[$position], null, $type, $derivation);
            }
        }
    }

    /**
     * Answers the types of the values a multiple-column source supplies, null for one not to check, or null when they are not known.
     *
     * A field of a row constructor that is DEFAULT, or whose type the row
     * reports as `text` (as it does for a string constant), is not checked.
     * A subquery of several columns yields an anonymous record of them; a
     * subquery of one column yields its value.
     *
     * @return list<TypeFact|null>|null
     */
    public function types(RowConstructor|ScalarSubquery $source, TypeFact $type): ?array
    {
        if (!$type instanceof Known) {
            return null;
        }
        if (!$type->descriptor instanceof Composite || ($source instanceof ScalarSubquery && $type->descriptor->relation !== null)) {
            return $source instanceof ScalarSubquery ? [$type] : null;
        }
        $types = [];
        foreach ($type->descriptor->fields as $position => $slot) {
            $field = $source instanceof RowConstructor ? $source->fields[$position] ?? null : null;
            $unchecked = $field instanceof DefaultRequest || ($field !== null && $slot->type instanceof Known && $slot->type->descriptor === Builtin::Text);
            $types[] = $unchecked ? null : $slot->type;
        }

        return $types;
    }

    /**
     * Checks a value assigned to a whole column; DEFAULT and an unknown column are not checked.
     */
    public function value(?OutputSlot $slot, ?Scalar $value, TypeFact $type, Derivation $derivation): void
    {
        if ($slot !== null && !$value instanceof DefaultRequest) {
            (new AssignmentCasts())->check($slot, $type, $derivation);
        }
    }

    /**
     * Answers the DEFAULT values a SET list may hold: the values of the items, and the fields of the row constructors of multiple-column items.
     *
     * @param list<Assignment|RowAssignment> $assignments
     *
     * @return list<DefaultRequest>
     */
    public function defaults(array $assignments): array
    {
        $defaults = [];
        foreach ($assignments as $assignment) {
            $values = $assignment instanceof Assignment ? [$assignment->value] : ($assignment->source instanceof RowConstructor ? $assignment->source->fields : []);
            foreach ($values as $value) {
                if ($value instanceof DefaultRequest) {
                    $defaults[] = $value;
                }
            }
        }

        return $defaults;
    }
}
