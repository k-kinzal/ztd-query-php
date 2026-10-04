<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Rules\Expression\RowValues;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Operators;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A prefix operation over one operand.
 *
 * Rule: SQLITE-UNARY-001. The facts follow SQLITE-OPERATOR-RESULT-001; the
 * operand is a single value (SQLITE-ROW-VALUE-USE-001). The
 * operand must keep its place without parentheses (SQLITE-PRECEDENCE-001):
 * it may not start with an operator weaker than the prefix operator.
 * Source: https://sqlite.org/lang_expr.html#operators_and_parse_affecting_attributes.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the operand of a negation
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT -a FROM t');
 *     $query->statement->columns[0]->expression->operand->name->value // => 'a'
 * @example Refusing an operand that the prefix operator would not cover when written
 *     $sum = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 + 2')->statement->columns[0]->expression;
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary(\SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator::Minus, $sum) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Unary implements Scalar
{
    use Snapshot;

    /**
     * @param UnaryOperator $operator The operator
     * @param Scalar $operand The operand
     */
    public function __construct(public readonly UnaryOperator $operator, public readonly Scalar $operand)
    {
        Check::input((new Precedence())->opening($operand) >= ($operator === UnaryOperator::Not ? Precedence::NEGATION : Precedence::PREFIX), 'The operand needs parentheses to keep its place.');
    }

    /**
     * Derives the operand and the result of the operator.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        (new RowValues())->single($operand, $derivation);

        return (new Operators())->unary($this->operator, $operand);
    }

    /**
     * Writes the operator before the operand.
     */
    public function render(Output $out): void
    {
        if ($this->operator === UnaryOperator::Not) {
            $out->keyword('NOT');
        } else {
            $out->symbol($this->operator->value);
        }
        $out->node($this->operand);
    }
}
