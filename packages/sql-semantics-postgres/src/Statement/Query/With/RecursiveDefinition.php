<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\With;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * A named relation whose query may refer to itself: a common table expression, or a recursive view.
 *
 * When such a relation is bound while its own query is derived, the second
 * operand of the UNION that computes it sees the relation with the columns of
 * the first operand, renamed by the column names written for it
 * (PG-SET-OPERATION-001). The server reads `CREATE RECURSIVE VIEW v (a) AS q`
 * as a view over `WITH RECURSIVE v (a) AS (q)`, so both kinds share the rule.
 * Source: https://www.postgresql.org/docs/17/queries-with.html#QUERIES-WITH-RECURSIVE,
 * https://www.postgresql.org/docs/17/sql-createview.html.
 *
 * @visibility public
 * @example Reading the recursive query of a common table expression
 *     $one = new \SqlSemantics\Platform\PostgreSql\Statement\Query\Select([new \SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')))]);
 *     $table = new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\CommonTableExpression(new \SqlSemantics\Statement\Identifier\Name('x'), $one, [new \SqlSemantics\Statement\Identifier\Name('a')]);
 *     [$table->recursiveQuery() === $one, $table->recursiveColumns()[0]->value] // => [true, 'a']
 */
interface RecursiveDefinition extends Node
{
    /**
     * Answers the query that computes the rows and may refer to the relation itself.
     */
    public function recursiveQuery(): Query;

    /**
     * Answers the column names written for the relation, in order; empty when none are written.
     *
     * @return list<Name>
     */
    public function recursiveColumns(): array;
}
