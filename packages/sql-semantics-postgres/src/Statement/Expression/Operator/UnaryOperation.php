<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\PrefixTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A prefix operator applied to one operand: `- a`, `+ a`, `@ a`, `OPERATOR(s.!!) a`.
 *
 * Mirrors PostgreSQL's `A_Expr` node of kind `AEXPR_OP` without a left
 * operand. The parser folds a minus before a numeric constant into a negative
 * constant; the model keeps the operator and types the pair as that constant.
 *
 * Rule: PG-UNARY-OPERATION-001. Facts follow PG-OPERATOR-TYPING-001. Only
 * `+`, `-`, an operator the scanner reads as a general operator, or the
 * `OPERATOR(...)` syntax can be written before an operand. The operand must
 * keep its place without parentheses (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-OPERATOR-CALLS,
 * https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-OPERATORS. Status: Implemented.
 *
 * @visibility public
 * @example Typing a negated constant as the constant it folds to
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT -2147483648')->field(0)->type->descriptor->name() // => 'integer'
 * @example Rejecting an operator that cannot be written before an operand
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName(new \SqlSemantics\Statement\Identifier\Name('*')), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class UnaryOperation implements Scalar
{
    use Snapshot;

    /**
     * The operators the scanner returns as their own tokens, of which only + and - can be written before an operand.
     */
    private const SELF = ['*', '/', '%', '^', '<', '>', '=', '<=', '>=', '<>'];

    /**
     * @param OperatorName $operator The operator
     * @param Scalar $operand The operand
     */
    public function __construct(public readonly OperatorName $operator, public readonly Scalar $operand)
    {
        Check::input($operator->explicit || $operator->qualifiers === [], 'An expression writes a qualified operator with OPERATOR(...).');
        Check::input($operator->explicit || !in_array($operator->name->value, self::SELF, true), 'This operator cannot be written before an operand without OPERATOR(...).');
        $precedence = new Precedence();
        Check::input($precedence->after($operand, $precedence->operator($operator, true)), 'The operand needs parentheses to keep its place.');
    }

    /**
     * Derives the operand and the result of the operator over its type.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);

        return (new PrefixTyping())->prefix($derivation->context, $this->operator, $operand, $this->operand);
    }

    /**
     * Writes the operator and the operand.
     */
    public function render(Output $out): void
    {
        $out->node($this->operator)->node($this->operand);
    }
}
