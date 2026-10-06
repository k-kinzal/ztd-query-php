<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalArithmetic;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the bit_expr level: the arithmetic and bit operators and interval arithmetic.
 *
 * Rule: MYSQL-BIT-EXPR-001. Scope: bit_expr. Every operator production is
 * left recursive; the left spine is walked in a loop and the operations
 * are built from the innermost outwards. `%` and MOD are one operator.
 * Constructs: Arithmetic, IntervalArithmetic. Terminates: the spine loop
 * descends one left child per step; every other child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/arithmetic-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/bit-functions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BitRule
{
    /**
     * The binary operator productions, by the operator they apply.
     */
    private const OPERATORS = [
        'bit_expr: bit_expr | bit_expr' => ArithmeticOperator::BitOr, 'bit_expr: bit_expr & bit_expr' => ArithmeticOperator::BitAnd,
        'bit_expr: bit_expr SHIFT_LEFT bit_expr' => ArithmeticOperator::ShiftLeft, 'bit_expr: bit_expr SHIFT_RIGHT bit_expr' => ArithmeticOperator::ShiftRight,
        'bit_expr: bit_expr + bit_expr' => ArithmeticOperator::Plus, 'bit_expr: bit_expr - bit_expr' => ArithmeticOperator::Minus,
        'bit_expr: bit_expr * bit_expr' => ArithmeticOperator::Multiply, 'bit_expr: bit_expr / bit_expr' => ArithmeticOperator::Divide,
        'bit_expr: bit_expr % bit_expr' => ArithmeticOperator::Modulo, 'bit_expr: bit_expr DIV_SYM bit_expr' => ArithmeticOperator::IntegerDivide,
        'bit_expr: bit_expr MOD_SYM bit_expr' => ArithmeticOperator::Modulo, 'bit_expr: bit_expr ^ bit_expr' => ArithmeticOperator::BitXor,
    ];

    /**
     * The interval arithmetic productions, by whether they subtract.
     */
    private const INTERVALS = ['bit_expr: bit_expr + INTERVAL_SYM expr interval' => false, 'bit_expr: bit_expr - INTERVAL_SYM expr interval' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of bit_expr.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function bitExpression(Node $expression): Scalar
    {
        $pending = [];
        $form = $this->lowering->form($expression);
        while (isset(self::OPERATORS[$form->signature]) || isset(self::INTERVALS[$form->signature])) {
            $pending[] = $form;
            $form = $this->lowering->form($form->node(0));
        }
        if ($form->signature !== 'bit_expr: simple_expr') {
            throw ImplementationGap::production($form);
        }
        $result = $this->lowering->expressions->simpleExpression($form->node(0));
        foreach (array_reverse($pending) as $operation) {
            $operator = self::OPERATORS[$operation->signature] ?? null;
            $result = $operator === null
                ? new IntervalArithmetic($result, $this->lowering->expressions->interval($operation->node(3), $operation->node(4)), self::INTERVALS[$operation->signature])
                : new Arithmetic($operator, $result, $this->bitExpression($operation->node(2)));
        }

        return $result;
    }
}
