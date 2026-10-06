<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Interval;
use SqlSemantics\Statement\Scalar;

/**
 * The entry rules of the expression family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-EXPRESSION-ENTRY-001. Scope: expr, bool_pri, predicate,
 * bit_expr, simple_expr, operators, CASE, casts, intervals, row
 * constructors, subquery expressions and MATCH. A method delegates to the
 * rule class of the level it lowers: MYSQL-CONDITION-001,
 * MYSQL-PREDICATE-001, MYSQL-BIT-EXPR-001, MYSQL-SIMPLE-EXPR-001,
 * MYSQL-EXPRESSION-LIST-001 and MYSQL-INTERVAL-UNIT-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/expressions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ExpressionRules
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an expression: a node of `expr`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function expression(Node $expression): Scalar
    {
        return (new ConditionRule($this->lowering))->expression($expression);
    }

    /**
     * Lowers an arithmetic or bit expression: a node of `bit_expr`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function bitExpression(Node $expression): Scalar
    {
        return (new BitRule($this->lowering))->bitExpression($expression);
    }

    /**
     * Lowers a primary expression: a node of `simple_expr`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function simpleExpression(Node $expression): Scalar
    {
        return (new PrimaryRule($this->lowering))->simpleExpression($expression);
    }

    /**
     * Lowers a comma-separated expression list: a node of `expr_list` or `opt_expr_list`; an absent list
     * is empty.
     *
     * @return list<Scalar>
     * @throws ImplementationGap When a production has no rule
     */
    public function expressions(Node $list): array
    {
        return (new ListRule($this->lowering))->expressions($list);
    }

    /**
     * Lowers an interval unit: a node of `interval` or `interval_time_stamp`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function intervalUnit(Node $unit): IntervalUnit
    {
        return (new IntervalRule($this->lowering))->unit($unit);
    }

    /**
     * Lowers the interval `INTERVAL quantity unit` from the node of its quantity (`expr`) and of its unit (`interval`).
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function interval(Node $quantity, Node $unit): Interval
    {
        return new Interval($this->expression($quantity), $this->intervalUnit($unit));
    }
}
