<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;

/**
 * Derives the facts of a set operation.
 *
 * Rule: MYSQL-SET-FACTS-001. Both operands are derived in the enclosing
 * environment, each its own query block; the output columns combine them by
 * MYSQL-RESULT-SLOTS-001. In the query of a recursive common table
 * expression (MYSQL-WITH-001) a right operand that refers to the table sees
 * it with the columns of the left operand, all nullable, and those columns
 * are the output, whose types the recursive part does not change. Terminates:
 * both operands are strict parts. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/set-operations.html,
 * https://dev.mysql.com/doc/refman/8.4/en/with.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SetFacts
{
    /**
     * Derives both operands and answers the output.
     */
    public function derive(SetOperation $operation, Derivation $derivation, Environment $outer): QueryFact
    {
        return $this->operands($operation->left, $operation->operator, $operation->right, $derivation, $outer);
    }

    /**
     * Derives the two operands of a set operator and answers the output.
     */
    public function operands(Query $leftQuery, SetOperator $operator, Query $rightQuery, Derivation $derivation, Environment $outer): QueryFact
    {
        $left = $derivation->query($leftQuery, $outer);
        $tables = new CommonTables();
        $pending = $tables->pending($outer, $rightQuery, $derivation);
        $right = $derivation->query($rightQuery, $pending === null ? $outer : $tables->anchored($outer, $pending, $left, $derivation));
        $fact = new QueryFact((new ResultSlots())->combine($left, $right, $operator, $derivation), $derivation->context->columnNames);

        return $pending === null ? $fact : $tables->nullable($left);
    }
}
