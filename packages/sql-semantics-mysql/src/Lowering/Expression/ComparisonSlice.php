<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the thin expression slice: comparisons of column references, literals, parameters and variables.
 *
 * Slice of the expression family, written with the leaf layers so that the
 * pipeline runs end to end; the expression family completes or replaces it.
 *
 * Rule: MYSQL-EXPRESSION-SLICE-001. Scope: the unit alternatives of expr,
 * bool_pri, predicate and bit_expr, the comparison alternative of bool_pri
 * with comp_op, and the simple_expr alternatives for a column reference, a
 * literal, a parameter marker and a variable. Operands keep their written
 * order; a chain of comparisons associates to the left as the grammar does.
 * Constructs: Comparison and the leaf values. Terminates: the left spine of
 * a comparison chain is walked in a loop; every other child is a strict
 * subtree. Source: https://dev.mysql.com/doc/refman/8.4/en/expressions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ComparisonSlice
{
    /**
     * The comparison operator productions.
     */
    private const OPERATORS = [
        'comp_op: EQ' => ComparisonOperator::Equal, 'comp_op: GE' => ComparisonOperator::GreaterOrEqual, 'comp_op: GT_SYM' => ComparisonOperator::Greater,
        'comp_op: LE' => ComparisonOperator::LessOrEqual, 'comp_op: LT' => ComparisonOperator::Less, 'comp_op: NE' => ComparisonOperator::NotEqual,
        'comp_op: EQUAL_SYM' => ComparisonOperator::NullSafeEqual,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an expression of the slice.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function expression(Node $expression): Scalar
    {
        $form = $this->lowering->productions->form($expression);
        if ($form->signature !== 'expr: bool_pri') {
            throw ImplementationGap::production($form);
        }
        $pending = [];
        $form = $this->lowering->productions->form($form->node(0));
        while ($form->signature === 'bool_pri: bool_pri comp_op predicate' || $form->signature === 'bool_pri: bool_pri EQUAL_SYM predicate') {
            $pending[] = [$form->signature === 'bool_pri: bool_pri EQUAL_SYM predicate' ? ComparisonOperator::NullSafeEqual : $this->operator($form->node(1)), $form->node(2)];
            $form = $this->lowering->productions->form($form->node(0));
        }
        if ($form->signature !== 'bool_pri: predicate') {
            throw ImplementationGap::production($form);
        }
        $result = $this->predicate($form->node(0));
        foreach (array_reverse($pending) as [$operator, $right]) {
            $result = new Comparison($operator, $result, $this->predicate($right));
        }

        return $result;
    }

    /**
     * Lowers a comparison operator.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function operator(Node $operator): ComparisonOperator
    {
        $form = $this->lowering->productions->form($operator);

        return self::OPERATORS[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers a predicate that is a plain arithmetic operand.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function predicate(Node $predicate): Scalar
    {
        $form = $this->lowering->productions->form($predicate);
        if ($form->signature !== 'predicate: bit_expr') {
            throw ImplementationGap::production($form);
        }

        return $this->bitExpression($form->node(0));
    }

    /**
     * Lowers an arithmetic operand that is a primary expression.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function bitExpression(Node $expression): Scalar
    {
        $form = $this->lowering->productions->form($expression);
        if ($form->signature !== 'bit_expr: simple_expr') {
            throw ImplementationGap::production($form);
        }

        return $this->simpleExpression($form->node(0));
    }

    /**
     * Lowers a primary expression that is a leaf.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function simpleExpression(Node $expression): Scalar
    {
        $form = $this->lowering->productions->form($expression);

        return match ($form->signature) {
            'simple_expr: simple_ident' => $this->lowering->names->column($form->node(0)),
            'simple_expr: literal', 'simple_expr: literal_or_null' => $this->lowering->literals->literal($form->node(0)),
            'simple_expr: param_marker' => $this->lowering->literals->parameter($form->node(0)),
            'simple_expr: variable', 'simple_expr: rvalue_system_or_user_variable', 'simple_expr: in_expression_user_variable_assignment' => $this->lowering->variables->variable($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }
}
