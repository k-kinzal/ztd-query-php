<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\TableShapes;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\TargetTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\RelationFact;

/**
 * Derives the table a data-modifying statement or COPY names.
 *
 * Rule: PG-TARGET-TABLE-001. The name denotes a relation found along the
 * schema search path; a common table expression of the same name is never
 * the target. The row shape follows PG-TABLE-SHAPE-001. The table is one
 * visible relation under its correlation name, or under its table name when
 * it has none; with a correlation name, the table name no longer qualifies
 * its columns.
 * Source: https://www.postgresql.org/docs/17/sql-update.html,
 * https://www.postgresql.org/docs/17/queries-with.html#QUERIES-WITH-MODIFYING. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Targets
{
    /**
     * Resolves the table among the declared relations and derives its row shape.
     */
    public function fact(TargetTable $target, Derivation $derivation): RelationFact
    {
        return (new TableShapes())->input(new TableInput($target->table, $target->alias), $derivation, $derivation->environment());
    }

    /**
     * Answers the table as the relation the clauses of the statement see.
     */
    public function visible(TargetTable $target, RelationFact $fact): VisibleRelation
    {
        return new VisibleRelation($target, $fact->shape, $target->alias, $target->alias === null ? $target->table->name : null, [], (new TableShapes())->implicit($fact));
    }
}
