<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Expression;

use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the binary and prefix operator productions.
 *
 * Rule: SQLITE-EXPR-OPERATOR-001. Scope: the `expr` productions of the
 * binary operators, the IS family and the prefix operators. The operator is
 * kept by its spelling and the operands in written order; the structure the
 * parser chose is the structure built. Terminates: every operand is a strict
 * subtree.
 * Source: https://sqlite.org/lang_expr.html#operators_and_parse_affecting_attributes.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class OperatorRule
{
    private readonly PredicateRule $predicates;

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
        $this->predicates = new PredicateRule($lowering);
    }

    /**
     * Lowers an operator expression, or passes the form on to the predicate rules.
     */
    public function expression(Form $form): Scalar
    {
        $expressions = $this->lowering->expressions;

        return match ($form->signature) {
            'expr: expr AND expr', 'expr: expr OR expr', 'expr: expr LT|GT|GE|LE expr', 'expr: expr EQ|NE expr',
            'expr: expr BITAND|BITOR|LSHIFT|RSHIFT expr', 'expr: expr PLUS|MINUS expr', 'expr: expr STAR|SLASH|REM expr',
            'expr: expr CONCAT expr', 'expr: expr PTR expr' => new Binary(BinaryOperator::from(strtoupper($form->token(1)->text)), $expressions->expression($form->node(0)), $expressions->expression($form->node(2))),
            'expr: expr IS expr' => new Binary(BinaryOperator::Is, $expressions->expression($form->node(0)), $expressions->expression($form->node(2))),
            'expr: expr IS NOT expr' => new Binary(BinaryOperator::IsNot, $expressions->expression($form->node(0)), $expressions->expression($form->node(3))),
            'expr: expr IS DISTINCT FROM expr' => new Binary(BinaryOperator::IsDistinctFrom, $expressions->expression($form->node(0)), $expressions->expression($form->node(4))),
            'expr: expr IS NOT DISTINCT FROM expr' => new Binary(BinaryOperator::IsNotDistinctFrom, $expressions->expression($form->node(0)), $expressions->expression($form->node(5))),
            'expr: NOT expr' => new Unary(UnaryOperator::Not, $expressions->expression($form->node(1))),
            'expr: BITNOT expr', 'expr: PLUS|MINUS expr' => new Unary(UnaryOperator::from($form->token(0)->text), $expressions->expression($form->node(1))),
            default => $this->predicates->expression($form),
        };
    }
}
