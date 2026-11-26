<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Sublinks;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;

/**
 * A query in parentheses used as a value: `(SELECT …)`, the value of its one row, or NULL when it returns none.
 *
 * Mirrors PostgreSQL's `SubLink` node of kind `EXPR_SUBLINK`; compared with
 * a row constructor it is a row comparison (`ROWCOMPARE_SUBLINK`).
 *
 * Rule: PG-SCALAR-SUBQUERY-001. The query is derived in the environment of
 * the expression, so it can refer to the enclosing queries. Facts: a query of
 * one column yields that column's type; a query of several columns yields a
 * row of them, which only a row comparison accepts; a query of an open
 * shape depends on its missing inputs; a query of no column is reported. The
 * value can always be NULL. An unaliased result column is named as the
 * query names its first column.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-SCALAR-SUBQUERIES. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ScalarSubquery implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Query $query The query inside the parentheses
     */
    public function __construct(public readonly Query $query)
    {
    }

    /**
     * Names an unaliased result column as the query names its first column.
     */
    public function outputName(): ?Name
    {
        return $this->query instanceof OutputNaming ? $this->query->outputName() : null;
    }

    /**
     * Derives the query and the value it yields.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->query($this->query, $environment);

        return new ScalarFact((new Sublinks())->value($derivation, $fact, 'a scalar subquery'), Nullability::Nullable);
    }

    /**
     * Writes the query in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->query)->symbol(')');
    }
}
