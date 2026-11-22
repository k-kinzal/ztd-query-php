<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Operator;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Rules\Expression\RowValueUse;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Operators;

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
 * @visibility SqlSemantics
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
        (new RowValueUse())->single($operand, $derivation);

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
