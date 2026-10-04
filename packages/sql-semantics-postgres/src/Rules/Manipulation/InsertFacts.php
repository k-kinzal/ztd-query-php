<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Categories;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\DefaultRequest;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\ColumnTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\ConflictDoNothing;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\ConflictDoUpdate;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\IndexInference;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertDefaults;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertSelect;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityRule;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;

/**
 * Derives the facts of an INSERT statement.
 *
 * Rule: PG-INSERT-001. The common tables and the target follow
 * PG-MODIFICATION-SCOPE-001; the columns follow PG-ASSIGNMENT-001. The
 * VALUES rows and the query see the common tables, not the target. Every
 * VALUES row must have as many values as the first. With a column list,
 * each row must supply one value per column; without one, at most one value
 * per column of the table, the rest taking their defaults; more or fewer
 * are reported when both counts are known. Each value of a VALUES row is
 * assigned to its column on its own (PG-ASSIGNMENT-CAST-001); an output
 * column of the query is checked the same way unless it is of a string
 * type, which a string constant of the query also has. The conflict target
 * sees the target only; DO UPDATE and its condition see the target and,
 * under the name `excluded`, the row proposed for insertion, each
 * qualifying the same columns, so an unqualified column of both is
 * ambiguous; DO UPDATE without a conflict target is reported. RETURNING
 * sees the target.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html,
 * https://www.postgresql.org/docs/17/sql-insert.html#SQL-ON-CONFLICT. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class InsertFacts
{
    /**
     * Derives an INSERT of VALUES rows and answers its rows.
     */
    public function rows(InsertRows $insert, Derivation $derivation, Environment $outer): QueryFact
    {
        $scope = new ModificationScope();
        [$base, $target] = $scope->open($insert->with, $insert->target, $derivation, $outer);
        $slots = $this->columns($insert->columns, $target, $derivation, $base);
        $defaults = [];
        $rows = $insert->rows->rows();
        $width = count($rows[0]->values);
        foreach ($rows as $row) {
            if (count($row->values) !== $width) {
                $derivation->report(new ArityMismatch(ArityRule::ValuesRows, '', $width, count($row->values)));
            }
            foreach ($row->values as $position => $value) {
                $fact = $derivation->scalar($value, $base);
                (new Assignments())->value($slots[$position] ?? null, $value, $fact->type, $derivation);
                if ($value instanceof DefaultRequest) {
                    $defaults[] = $value;
                }
            }
        }
        $this->arity($insert->columns, $target, $width, $derivation);
        $defaults = [...$defaults, ...$this->conflict($insert->conflict, $target, $derivation, $base)];
        (new Placement())->values($insert, $defaults, [], $derivation);

        return $scope->returning($insert->returning, [$target], $derivation, $base);
    }

    /**
     * Derives an INSERT of the rows of a query and answers its rows.
     */
    public function select(InsertSelect $insert, Derivation $derivation, Environment $outer): QueryFact
    {
        $scope = new ModificationScope();
        [$base, $target] = $scope->open($insert->with, $insert->target, $derivation, $outer);
        $slots = $this->columns($insert->columns, $target, $derivation, $base);
        $rows = $derivation->query($insert->source, $base);
        $fields = $rows->fields();
        if ($fields !== null) {
            $categories = new Categories();
            foreach ($fields as $position => $field) {
                $type = $categories->builtin($field->type);
                if ($type === null || !$categories->textual($type)) {
                    (new Assignments())->value($slots[$position] ?? null, null, $field->type, $derivation);
                }
            }
            $this->arity($insert->columns, $target, $fields->count(), $derivation);
        }
        $defaults = $this->conflict($insert->conflict, $target, $derivation, $base);
        (new Placement())->values($insert, $defaults, [], $derivation);

        return $scope->returning($insert->returning, [$target], $derivation, $base);
    }

    /**
     * Derives an INSERT of the column defaults and answers its rows.
     */
    public function defaults(InsertDefaults $insert, Derivation $derivation, Environment $outer): QueryFact
    {
        $scope = new ModificationScope();
        [$base, $target] = $scope->open($insert->with, $insert->target, $derivation, $outer);
        $defaults = $this->conflict($insert->conflict, $target, $derivation, $base);
        (new Placement())->values($insert, $defaults, [], $derivation);

        return $scope->returning($insert->returning, [$target], $derivation, $base);
    }

    /**
     * Derives the column list and answers the slot each value is assigned to: the columns listed, or every column of the table.
     *
     * @param list<ColumnTarget> $columns
     *
     * @return list<OutputSlot|null>
     */
    public function columns(array $columns, VisibleRelation $target, Derivation $derivation, Environment $base): array
    {
        if ($columns === []) {
            return $target->shape->slots;
        }

        return (new Assignments())->columns($columns, $target, $derivation, $base, ManipulationMisuseRule::RepeatedInsertColumn);
    }

    /**
     * Reports a number of values per row that does not fit the columns.
     *
     * @param list<ColumnTarget> $columns
     */
    public function arity(array $columns, VisibleRelation $target, int $values, Derivation $derivation): void
    {
        $count = $columns !== [] ? count($columns) : ($target->shape->complete() && $target->shape->slots !== [] ? count($target->shape->slots) : null);
        if ($count !== null && $values > $count) {
            $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::MoreExpressions));
        }
        if ($columns !== [] && $values < $count) {
            $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::MoreTargetColumns));
        }
    }

    /**
     * Derives the ON CONFLICT clause and answers the DEFAULT values it admits.
     *
     * @return list<DefaultRequest>
     */
    public function conflict(ConflictDoNothing|ConflictDoUpdate|null $conflict, VisibleRelation $target, Derivation $derivation, Environment $base): array
    {
        if ($conflict === null) {
            return [];
        }
        if ($conflict->target instanceof IndexInference) {
            $environment = new Environment($derivation->context, $base, [$target]);
            foreach ($conflict->target->elements as $element) {
                $element->deriveClause($derivation, $environment);
            }
            if ($conflict->target->where !== null) {
                $derivation->scalar($conflict->target->where, $environment);
            }
        }
        if (!$conflict instanceof ConflictDoUpdate) {
            return [];
        }
        if ($conflict->target === null) {
            $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::ConflictUpdateWithoutTarget));
        }
        $excluded = new VisibleRelation($target->relation, $target->shape, new Name('excluded'), null, [], $target->implicit);
        $environment = new Environment($derivation->context, $base, [$target, $excluded]);
        $assignments = new Assignments();
        $assignments->assign($conflict->assignments, $target, $derivation, $environment);
        if ($conflict->where !== null) {
            $derivation->scalar($conflict->where, $environment);
        }

        return $assignments->defaults($conflict->assignments);
    }
}
