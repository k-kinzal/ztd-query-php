<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Mutation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Query\CommonTables;
use SqlSemantics\Platform\Sqlite\Rules\Query\Projection;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\Joining;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\RowAssignment;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithClause;
use SqlSemantics\Platform\Sqlite\Statement\Type\Vector;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Type\Known;

/**
 * Derives the parts that INSERT, UPDATE and DELETE share.
 *
 * Rule: SQLITE-MUTATION-SCOPE-001. The common tables of the statement are
 * bound first and are visible in every query of the statement. The written
 * table is one visible relation under its correlation name, or its table
 * name when it has none. A column named by a column list or an assignment
 * must be a column of the written table, or its rowid; a name the table
 * certainly lacks is reported. A row assignment needs a row of as many
 * values as columns. RETURNING sees the written table only; its result
 * columns follow SQLITE-RESULT-NAME-001 and are the output of the statement.
 * Source: https://sqlite.org/lang_update.html, https://sqlite.org/lang_returning.html,
 * https://sqlite.org/lang_with.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class MutationScope
{
    /**
     * Binds the common tables and derives the written table.
     *
     * @return array{Environment, VisibleRelation} The environment the queries of the statement are nested in, and the written table as a visible relation
     */
    public function open(MutationTarget $target, ?WithClause $with, Derivation $derivation, Environment $outer): array
    {
        $base = $with === null ? $outer : (new CommonTables())->bind($with, $derivation, $outer);
        $fact = $derivation->relation($target, $base);

        return [$base, new VisibleRelation($target, $fact->shape, $target->alias, $target->name, [], (new TableShapes())->implicit($fact))];
    }

    /**
     * Reports the names that are certainly no column of the written table.
     *
     * @param list<Name> $columns
     */
    public function names(array $columns, VisibleRelation $target, Derivation $derivation): void
    {
        $joining = new Joining();
        foreach ($columns as $column) {
            $known = $joining->locate([$target], $column->value, $derivation) !== null;
            foreach ($target->implicit as $implicit) {
                foreach ($implicit->names as $name) {
                    $known = $known || $derivation->context->columnNames->equal($name->value, $column->value);
                }
            }
            if (!$known && $joining->closed([$target])) {
                $derivation->report(new MissingColumn($column));
            }
        }
    }

    /**
     * Derives the assignments of an UPDATE or of an upsert.
     *
     * @param list<Assignment|RowAssignment> $assignments
     */
    public function assign(array $assignments, VisibleRelation $target, Derivation $derivation, Environment $environment): void
    {
        foreach ($assignments as $assignment) {
            $fact = $derivation->scalar($assignment->value, $environment);
            $columns = $assignment instanceof Assignment ? [$assignment->column] : $assignment->columns;
            $this->names($columns, $target, $derivation);
            if ($assignment instanceof RowAssignment && $fact->type instanceof Known) {
                $width = $fact->type->descriptor instanceof Vector ? $fact->type->descriptor->width : 1;
                if ($width !== count($columns)) {
                    $derivation->report(new ArityMismatch(ArityRule::RowAssignment, count($columns), $width));
                }
            }
        }
    }

    /**
     * Derives a RETURNING clause and answers the rows it returns, or null when there is none.
     *
     * @param list<ResultColumn|Star|TableStar> $columns
     */
    public function returning(array $columns, VisibleRelation $target, Derivation $derivation, Environment $base): ?QueryFact
    {
        if ($columns === []) {
            return null;
        }

        return new QueryFact((new Projection())->items($columns, $derivation, new Environment($derivation->context, $base, [$target])), $derivation->context->columnNames);
    }
}
