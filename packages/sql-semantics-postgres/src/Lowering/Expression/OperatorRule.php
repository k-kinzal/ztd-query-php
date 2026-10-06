<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Expression;

use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\CastSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Collation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\AtLocal;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\AtTimeZone;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\BooleanOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\BooleanOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\DistinctTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Negation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\DocumentTest;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the operator productions of `a_expr` and `b_expr`.
 *
 * Rule: PG-EXPRESSION-OPERATOR-001. Scope: the binary arithmetic and
 * comparison operators, a general or `OPERATOR(...)` operator between or
 * before operands, the prefix `+` and `-`, AND, OR, NOT, `::`, COLLATE, AT
 * TIME ZONE, AT LOCAL (17), IS [NOT] DISTINCT FROM and IS [NOT] DOCUMENT.
 * Constructors: `BinaryOperation`, `UnaryOperation`, `BooleanOperation`,
 * `Negation`, `Cast`, `Collation`, `AtTimeZone`, `AtLocal`,
 * `DistinctTest`, `DocumentTest`. NOT and the lookahead NOT_LA are the same
 * keyword. Termination: each operand is a strictly smaller subtree.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-OPERATOR-CALLS. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class OperatorRule
{
    /**
     * The productions of an operator token between two operands.
     */
    private const SYMBOLS = [
        'a_expr: a_expr + a_expr', 'a_expr: a_expr - a_expr', 'a_expr: a_expr * a_expr', 'a_expr: a_expr / a_expr', 'a_expr: a_expr % a_expr',
        'a_expr: a_expr ^ a_expr', 'a_expr: a_expr < a_expr', 'a_expr: a_expr > a_expr', 'a_expr: a_expr = a_expr',
        'a_expr: a_expr LESS_EQUALS a_expr', 'a_expr: a_expr GREATER_EQUALS a_expr', 'a_expr: a_expr NOT_EQUALS a_expr',
        'b_expr: b_expr + b_expr', 'b_expr: b_expr - b_expr', 'b_expr: b_expr * b_expr', 'b_expr: b_expr / b_expr', 'b_expr: b_expr % b_expr',
        'b_expr: b_expr ^ b_expr', 'b_expr: b_expr < b_expr', 'b_expr: b_expr > b_expr', 'b_expr: b_expr = b_expr',
        'b_expr: b_expr LESS_EQUALS b_expr', 'b_expr: b_expr GREATER_EQUALS b_expr', 'b_expr: b_expr NOT_EQUALS b_expr',
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an operator production, or answers null when the production is not one.
     */
    public function lower(Form $form): ?Scalar
    {
        if (in_array($form->signature, self::SYMBOLS, true)) {
            return $this->lowering->expressions->comparison($form);
        }

        return $this->operators($form) ?? $this->postfix($form);
    }

    /**
     * Lowers the general, prefix and boolean operators.
     */
    public function operators(Form $form): ?Scalar
    {
        $expressions = $this->lowering->expressions;

        return match ($form->signature) {
            'a_expr: a_expr qual_Op a_expr', 'b_expr: b_expr qual_Op b_expr' => new BinaryOperation(
                $this->lowering->operators->operator($form->node(1)),
                $expressions->expression($form->node(0)),
                $expressions->expression($form->node(2)),
            ),
            'a_expr: qual_Op a_expr', 'b_expr: qual_Op b_expr' => new UnaryOperation($this->lowering->operators->operator($form->node(0)), $expressions->expression($form->node(1))),
            'a_expr: + a_expr', 'a_expr: - a_expr', 'b_expr: + b_expr', 'b_expr: - b_expr' => new UnaryOperation(
                new OperatorName($this->lowering->operators->symbol($form->token(0))),
                $expressions->expression($form->node(1)),
            ),
            'a_expr: a_expr AND a_expr' => new BooleanOperation(BooleanOperator::And, $expressions->expression($form->node(0)), $expressions->expression($form->node(2))),
            'a_expr: a_expr OR a_expr' => new BooleanOperation(BooleanOperator::Or, $expressions->expression($form->node(0)), $expressions->expression($form->node(2))),
            'a_expr: NOT a_expr', 'a_expr: NOT_LA a_expr' => new Negation($expressions->expression($form->node(1))),
            default => null,
        };
    }

    /**
     * Lowers the operators written after their first operand.
     */
    public function postfix(Form $form): ?Scalar
    {
        $expressions = $this->lowering->expressions;

        return match ($form->signature) {
            'a_expr: a_expr TYPECAST Typename', 'b_expr: b_expr TYPECAST Typename' => new Cast(
                $expressions->expression($form->node(0)),
                $this->lowering->types->typeName($form->node(2)),
                CastSpelling::Operator,
            ),
            'a_expr: a_expr COLLATE any_name' => new Collation($expressions->expression($form->node(0)), $this->lowering->names->dotted($form->node(2))),
            'a_expr: a_expr AT TIME ZONE a_expr' => new AtTimeZone($expressions->expression($form->node(0)), $expressions->expression($form->node(4))),
            'a_expr: a_expr AT LOCAL' => new AtLocal($expressions->expression($form->node(0))),
            'a_expr: a_expr IS DISTINCT FROM a_expr', 'b_expr: b_expr IS DISTINCT FROM b_expr' => new DistinctTest(
                $expressions->expression($form->node(0)),
                $expressions->expression($form->node(4)),
                false,
            ),
            'a_expr: a_expr IS NOT DISTINCT FROM a_expr', 'b_expr: b_expr IS NOT DISTINCT FROM b_expr' => new DistinctTest(
                $expressions->expression($form->node(0)),
                $expressions->expression($form->node(5)),
                true,
            ),
            'a_expr: a_expr IS DOCUMENT_P', 'b_expr: b_expr IS DOCUMENT_P' => new DocumentTest($expressions->expression($form->node(0)), false),
            'a_expr: a_expr IS NOT DOCUMENT_P', 'b_expr: b_expr IS NOT DOCUMENT_P' => new DocumentTest($expressions->expression($form->node(0)), true),
            default => null,
        };
    }
}
