<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Rendering\Output;

/**
 * Writes what follows the first operand of a set operation and the body of a query expression.
 *
 * Rule: MYSQL-QUERY-TAIL-001. A set operation and a leading union are
 * written as the left operand, the operator, the written quantifier and
 * the right operand; a
 * query expression as its WITH clause, its body, its ORDER BY and its
 * LIMIT. The parts after the left operand and after the body are written
 * here only, so a writer that has to place something inside the first
 * operand (the 5.x enclosed partitioning of CREATE TABLE … SELECT) writes
 * the rest exactly as the query does. Terminates: the operands are written
 * by their own classes.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-operations.html,
 * https://dev.mysql.com/doc/refman/8.4/en/select.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class QueryTails
{
    /**
     * Writes the operator, the quantifier and the right operand of a set operation.
     */
    public function operation(SetOperation $operation, Output $out): void
    {
        $out->keyword($operation->operator->value);
        if ($operation->quantifier !== null) {
            $out->keyword($operation->quantifier->value);
        }
        $out->node($operation->right);
    }

    /**
     * Writes UNION, the quantifier and the last operand of a leading union.
     */
    public function leading(LeadingUnion $leading, Output $out): void
    {
        $out->keyword(SetOperator::Union->value);
        if ($leading->quantifier !== null) {
            $out->keyword($leading->quantifier->value);
        }
        $out->node($leading->right);
    }

    /**
     * Writes the ORDER BY and the LIMIT of a query expression.
     */
    public function expression(QueryExpression $expression, Output $out): void
    {
        if ($expression->orderBy !== []) {
            $out->keyword('ORDER', 'BY')->list($expression->orderBy);
        }
        $out->node($expression->limit);
    }
}
