<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Relation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;

/**
 * The entry of an environment that hands the clauses of a query expression to the selection inside its parentheses.
 *
 * Rule: PG-QUERY-OPTIONS-001 (see `Carriers`). The carrier travels as a
 * visible occurrence with neither alias nor name and without any column, so
 * no column name, qualifier or star can reach it; only `Carriers::take()`
 * recognises it, by its class. It is a working value of one derivation and
 * never part of a statement.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-ORDERBY. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Carrier implements Relation
{
    /**
     * @param QueryExpression $expression The query expression whose clauses are handed down
     */
    public function __construct(public readonly QueryExpression $expression)
    {
    }

    /**
     * Answers the row without columns a carrier contributes.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return new RelationFact(new RowShape([]));
    }

    /**
     * Writes nothing: a carrier is not written.
     */
    public function render(Output $out): void
    {
    }
}
