<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\SubqueryRows;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A subquery used as a value: `(SELECT …)`, a scalar or row subquery (`Item_singlerow_subselect`).
 *
 * The subquery sees the enclosing positions as its outer scope.
 *
 * Rule: MYSQL-SCALAR-SUBQUERY-001. Facts: MYSQL-SUBQUERY-ROWS-001; a
 * subquery of one column has its type, one of several columns is a row,
 * and either is NULL when no row is returned. Terminates: the query is a
 * strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/scalar-subqueries.html,
 * https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Comparing with a scalar subquery
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a = (SELECT 1)');
 *     $query->statement->where->right instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery // => true
 */
final class ScalarSubquery implements Scalar
{
    use Snapshot;

    /**
     * @param Query $query The query
     */
    public function __construct(public readonly Query $query)
    {
    }

    /**
     * Derives the query with the enclosing position as outer scope, and the value it yields.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return (new SubqueryRows())->value($derivation->query($this->query, $environment));
    }

    /**
     * Writes the query in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->query)->symbol(')');
    }
}
