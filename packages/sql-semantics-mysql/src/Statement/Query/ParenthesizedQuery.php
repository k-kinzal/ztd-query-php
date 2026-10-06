<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A query written in parentheses where the parentheses group it.
 *
 * The parentheses keep a query in its place: an operand of a set operation
 * with its own ordering, a query with a WITH clause, or a parenthesized
 * statement. The parentheses that a subquery, a derived table or a common
 * table expression requires belong to that construct and are not a
 * parenthesized query; only further parentheses are.
 *
 * Rule: MYSQL-PARENTHESIZED-QUERY-001. The rows are those of the inner
 * query. Source: https://dev.mysql.com/doc/refman/8.4/en/union.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a parenthesized operand
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('(SELECT a FROM t ORDER BY a LIMIT 1) UNION SELECT b FROM u');
 *     $query->statement->left->query->limit !== null // => true
 */
final class ParenthesizedQuery implements Statement, Query
{
    use Snapshot;

    /**
     * @param Query $query The query inside the parentheses
     */
    public function __construct(public readonly Query $query)
    {
    }

    /**
     * Derives the query as a statement root and records its rows as the output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->output($derivation->query($this, $derivation->environment()));
    }

    /**
     * Answers the rows of the inner query.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        return $derivation->query($this->query, $outer);
    }

    /**
     * Writes the query in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->query)->symbol(')');
    }
}
