<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\QueryRoots;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;

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
 * @visibility SqlSemantics
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
