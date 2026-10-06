<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\QueryRoots;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A query in parentheses where the grammar treats the parentheses as grouping.
 *
 * Mirrors `select_with_parens`: PostgreSQL keeps no node for the
 * parentheses, so the query has the output of the query inside, and clauses
 * written after the parentheses apply to that query. The parentheses that a
 * subquery or a derived table requires are written by their holder, not by
 * this node.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-UNION.
 *
 * @visibility public
 * @example Keeping the grouping of set operations
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 UNION (SELECT 2 UNION SELECT 3)');
 *     [$query->statement->right instanceof \SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery, $query->toString()] // => [true, 'SELECT 1 UNION (SELECT 2 UNION SELECT 3)']
 */
final class ParenthesizedQuery implements Statement, Query, OutputNaming
{
    use Snapshot;

    /**
     * @param Query $query The query inside the parentheses
     */
    public function __construct(public readonly Query $query)
    {
    }

    /**
     * Answers the name of the first output column of the query inside.
     */
    public function outputName(): ?Name
    {
        return $this->query instanceof OutputNaming ? $this->query->outputName() : null;
    }

    /**
     * Derives the query as a statement root.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new QueryRoots())->derive($this, $derivation);
    }

    /**
     * Derives the query inside, whose output it has.
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
