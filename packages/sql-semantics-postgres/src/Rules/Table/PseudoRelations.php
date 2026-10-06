<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\Visibility;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;

/**
 * Builds the scope where OLD and NEW stand for the old and new row of a table, as triggers and rules see them.
 *
 * Rule: PG-OLD-NEW-001. OLD and NEW are two relations with the row shape of
 * the table (system columns included). In a trigger's WHEN condition and a
 * rule's WHERE condition both can be read by column name alone, so a name
 * that is a column of the table is ambiguous unless qualified; in the actions
 * of a rule they are reachable with a qualifier only.
 * Source: https://www.postgresql.org/docs/17/sql-createtrigger.html,
 * https://www.postgresql.org/docs/17/sql-createrule.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class PseudoRelations
{
    /**
     * Answers the scope with OLD and NEW.
     *
     * @param Relation $relation The statement node that stands for the table
     * @param bool $qualifiedOnly Whether OLD and NEW are reachable with a qualifier only
     */
    public function scope(Derivation $derivation, Relation $relation, RelationFact $fact, bool $qualifiedOnly): Environment
    {
        $implicit = (new Targets())->implicit($fact);
        $relations = [];
        foreach (['old', 'new'] as $alias) {
            $visible = new VisibleRelation($relation, $fact->shape, new Name($alias), null, [], $implicit);
            $relations[] = $qualifiedOnly ? (new Visibility())->qualifiedOnly($visible) : $visible;
        }

        return new Environment($derivation->context, null, $relations);
    }
}
