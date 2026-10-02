<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Statement\Scalar;

/**
 * The entry rules of the expression family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-EXPRESSION-ENTRY-001. Scope: expr, bool_pri, predicate, bit_expr, simple_expr, operators, CASE,
 * casts, intervals, row constructors and MATCH.
 * The method names, parameters and return types are fixed by the family
 * plan. A method delegates to the rule classes of this family; a method
 * the family has not implemented reports a missing rule.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/expressions.html.
 * The methods marked as slice run the thin vertical slice written with the
 * leaf layers; the family completes or replaces them.
 * Status: Specified.
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
     * @throws ImplementationGap When a production is outside the slice the family has yet to complete
     */
    public function expression(Node $expression): Scalar
    {
        return (new ComparisonSlice($this->lowering))->expression($expression);
    }

    /**
     * Lowers an arithmetic or bit expression: a node of `bit_expr`.
     *
     * @throws ImplementationGap When a production is outside the slice the family has yet to complete
     */
    public function bitExpression(Node $expression): Scalar
    {
        return (new ComparisonSlice($this->lowering))->bitExpression($expression);
    }

    /**
     * Lowers a primary expression: a node of `simple_expr`.
     *
     * @throws ImplementationGap When a production is outside the slice the family has yet to complete
     */
    public function simpleExpression(Node $expression): Scalar
    {
        return (new ComparisonSlice($this->lowering))->simpleExpression($expression);
    }

    /**
     * Lowers a comma-separated expression list: a node of `expr_list` or `opt_expr_list`; an absent list
     * is empty.
     *
     * @return list<Scalar>
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function expressions(Node $list): array
    {
        throw ImplementationGap::rule('MySQL expression family: expressions');
    }

    /**
     * Lowers an interval unit: a node of `interval` or `interval_time_stamp`.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function intervalUnit(Node $unit): IntervalUnit
    {
        throw ImplementationGap::rule('MySQL expression family: intervalUnit');
    }
}
