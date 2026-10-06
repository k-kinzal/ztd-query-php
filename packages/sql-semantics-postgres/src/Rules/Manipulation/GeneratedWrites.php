<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\Assignment;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\ColumnTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\RowAssignment;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateRule;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\OutputSlot;

/**
 * Reports a generated column that a data-modifying statement writes with a value other than DEFAULT.
 *
 * Rule: PG-GENERATED-WRITE-001. A generated column takes only DEFAULT,
 * whatever OVERRIDING clause the statement has. An INSERT, the INSERT action
 * of MERGE included, writes a column of its column list (or, without one, the
 * column at the position of a value) with a value other than DEFAULT unless
 * every row of its VALUES gives DEFAULT there; the rows of a query are never
 * DEFAULT. An UPDATE, the UPDATE action of MERGE and ON CONFLICT DO UPDATE
 * included, writes a column of its SET list with a value other than DEFAULT
 * unless the item, or the field of the row constructor of a multiple-column
 * item, is DEFAULT; a sub-SELECT is never DEFAULT, and assigning a value to
 * a part of the column (a subscript or a field) writes the column. Parentheses around
 * DEFAULT do not change it. The server checks the columns in table order
 * after parse analysis (rewriteTargetListIU, rewriteHandler.c), so the first
 * generated column so written is reported: `cannot insert a non-DEFAULT
 * value into column "b"` or `column "b" can only be updated to DEFAULT`,
 * both with the detail `Column "b" is a generated column.`. A column counts
 * when its slot is a declared generated column of the target; through a view
 * the base column is not followed. An action of CREATE RULE is not checked:
 * the server stores it without rewriting it, and the check applies only when
 * the rule fires; a statement of a routine body (BEGIN ATOMIC) is checked.
 * Source: https://www.postgresql.org/docs/17/ddl-generated-columns.html,
 * https://www.postgresql.org/docs/16/ddl-generated-columns.html,
 * https://github.com/postgres/postgres/blob/REL_17_2/src/backend/rewrite/rewriteHandler.c. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class GeneratedWrites
{
    /**
     * Reports the first generated column an INSERT writes with a value other than DEFAULT.
     *
     * @param list<ColumnTarget> $columns The column list; empty to write the columns of the table in order
     * @param list<list<Scalar>> $rows The VALUES rows; empty when a query supplies the rows
     * @param int|null $width The number of values per row, or null when it is not known
     * @param Environment $environment The environment of the statement
     */
    public function inserted(VisibleRelation $target, array $columns, array $rows, ?int $width, Derivation $derivation, Environment $environment): void
    {
        if (!$this->checked($environment)) {
            return;
        }
        $slots = $columns === [] ? $target->shape->slots : $this->targets($columns, $target, $derivation);
        $width ??= $columns === [] ? 0 : count($columns);
        $assignments = new Assignments();
        $written = [];
        foreach (array_slice($slots, 0, $width) as $position => $slot) {
            $defaulted = $rows !== [];
            foreach ($rows as $row) {
                $defaulted = $defaulted && $assignments->requested($row[$position] ?? null) !== null;
            }
            if ($slot !== null && !$defaulted) {
                $written[] = $slot;
            }
        }
        $this->report($target, $written, ManipulationMisuseRule::GeneratedInsert, $derivation);
    }

    /**
     * Reports the first generated column a SET list writes with a value other than DEFAULT.
     *
     * @param list<Assignment|RowAssignment> $assignments
     * @param Environment $environment The environment of the SET list
     */
    public function updated(VisibleRelation $target, array $assignments, Derivation $derivation, Environment $environment): void
    {
        if (!$this->checked($environment)) {
            return;
        }
        $rules = new Assignments();
        $written = [];
        foreach ($assignments as $assignment) {
            $columns = $assignment instanceof Assignment ? [$assignment->column] : $assignment->columns;
            $source = $assignment instanceof RowAssignment ? $rules->bare($assignment->source) : null;
            foreach ($this->targets($columns, $target, $derivation) as $position => $slot) {
                $value = $assignment instanceof Assignment ? $assignment->value : ($source instanceof RowConstructor ? $source->fields[$position] ?? null : null);
                if ($slot !== null && $rules->requested($value) === null) {
                    $written[] = $slot;
                }
            }
        }
        $this->report($target, $written, ManipulationMisuseRule::GeneratedUpdate, $derivation);
    }

    /**
     * Tells whether the server rewrites the statement where it is written: not inside an action of CREATE RULE, whose scope holds OLD and NEW of the rule.
     */
    public function checked(Environment $environment): bool
    {
        for ($scope = $environment; $scope !== null; $scope = $scope->outer) {
            foreach ($scope->relations as $relation) {
                if ($relation->relation instanceof CreateRule) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Answers the slot of the target each listed column writes, whole or in part, or null for a column the target does not show.
     *
     * @param list<ColumnTarget> $columns
     *
     * @return list<OutputSlot|null>
     */
    public function targets(array $columns, VisibleRelation $target, Derivation $derivation): array
    {
        $assignments = new Assignments();

        return array_map(static fn (ColumnTarget $column): ?OutputSlot => $assignments->find($column, $target, $derivation), $columns);
    }

    /**
     * Reports the first slot of the target, in table order, that is written and is a generated column.
     *
     * @param list<OutputSlot> $written
     */
    public function report(VisibleRelation $target, array $written, ManipulationMisuseRule $rule, Derivation $derivation): void
    {
        foreach ($target->shape->slots as $slot) {
            $column = $slot->declaration();
            if ($column !== null && $column->generated && in_array($slot, $written, true)) {
                $derivation->report(new ManipulationMisuse($rule, $column->name->value));

                return;
            }
        }
    }
}
