<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;

/**
 * Finds the relations of the source query of INSERT ... SELECT that ON DUPLICATE KEY UPDATE may refer to.
 *
 * Rule: MYSQL-INSERT-SOURCE-001. When the source is one query block without
 * GROUP BY, the values of ON DUPLICATE KEY UPDATE may refer to the columns
 * of the tables, derived tables and table functions of its FROM clause; a
 * set operation offers none. The occurrences are the ones the source query
 * has derived, with the shapes recorded for them. Terminates: an explicit
 * work stack over the finite FROM tree. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/insert-on-duplicate.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SourceRelations
{
    /**
     * Answers the relations of the FROM clause of a derived source query.
     *
     * @return list<VisibleRelation>
     */
    public function visible(Query $source, Derivation $derivation): array
    {
        if (!$source instanceof Select || $source->groupBy !== null || $source->from === null) {
            return [];
        }
        $facts = $derivation->facts();
        $visible = [];
        $pending = [$source->from];
        while ($pending !== []) {
            $relation = array_shift($pending);
            $nested = $this->members($relation);
            if ($nested !== null) {
                $pending = [...$nested, ...$pending];
                continue;
            }
            if (!$facts->covers($relation)) {
                continue;
            }
            $shape = $facts->relation($relation)->shape;
            if ($relation instanceof NamedRelation) {
                $visible[] = new VisibleRelation($relation, $shape, $relation->alias(), $relation->name());
            } elseif ($relation instanceof DerivedTable || $relation instanceof JsonTable) {
                $visible[] = new VisibleRelation($relation, $shape, $relation->alias);
            }
        }

        return $visible;
    }

    /**
     * Answers the relations a composite relation is made of, or null for a leaf.
     *
     * @return list<Relation>|null
     */
    public function members(Relation $relation): ?array
    {
        return match (true) {
            $relation instanceof TableList => $relation->members,
            $relation instanceof JoinedTable => [$relation->left, $relation->right],
            $relation instanceof NestedRelation, $relation instanceof OdbcJoin, $relation instanceof EscapedRelation => [$relation->relation],
            default => null,
        };
    }
}
