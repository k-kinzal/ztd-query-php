<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\QueryExpressionFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\QueryRoots;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\WithClause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A query body with a WITH clause before it, or ORDER BY, LIMIT and locking clauses after it.
 *
 * Mirrors `select_no_parens` with clauses around a `select_clause`.
 * PostgreSQL attaches the clauses to the query inside any parentheses, so
 * ORDER BY after a parenthesized selection still sees its FROM items. A
 * selection and a TABLE query hold the clauses written directly after them
 * themselves; this node then holds only the WITH clause. The facts follow
 * PG-QUERY-EXPRESSION-001.
 * Source: https://www.postgresql.org/docs/17/sql-select.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading ORDER BY after a set operation
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 AS a UNION SELECT 2 ORDER BY a');
 *     [$query->statement->body instanceof \SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation, count($query->statement->options->order)] // => [true, 1]
 * @example Refusing clauses that belong to a selection
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression(null, new \SqlSemantics\Platform\PostgreSql\Statement\Query\Select([]), new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions(readOnly: true)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class QueryExpression implements Statement, Query, OutputNaming
{
    use Snapshot;

    /**
     * @param WithClause|null $with The WITH clause
     * @param Query $body The query body
     * @param SelectOptions|null $options The ORDER BY, LIMIT and locking clauses written after the body
     */
    public function __construct(public readonly ?WithClause $with, public readonly Query $body, public readonly ?SelectOptions $options = null)
    {
        Check::input($with !== null || $options !== null, 'A query expression adds a WITH clause or clauses after its body.');
        Check::input(
            $body instanceof Select || $body instanceof TableQuery || $body instanceof SetOperation || $body instanceof ValuesList || $body instanceof ParenthesizedQuery,
            'A query body is a selection, TABLE, a set operation, VALUES or a parenthesized query.',
        );
        Check::input($options === null || (!$body instanceof Select && !$body instanceof TableQuery), 'A selection and a TABLE query hold the clauses written after them.');
    }

    /**
     * Answers the name of the first output column of the body.
     */
    public function outputName(): ?Name
    {
        return $this->body instanceof OutputNaming ? $this->body->outputName() : null;
    }

    /**
     * Derives the query as a statement root.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new QueryRoots())->derive($this, $derivation);
    }

    /**
     * Derives the WITH clause, the body and the clauses after it.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        return (new QueryExpressionFacts())->derive($this, $derivation, $outer);
    }

    /**
     * Writes the WITH clause, the body and the clauses after it.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->node($this->body)->node($this->options);
    }
}
