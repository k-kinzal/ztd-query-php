<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\NumericResult;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A prefix operator applied to a simple_expr: `+x`, `-x`, `~x`, `!x` (`Item_func_neg`, `Item_func_bit_neg`, `Item_func_not`).
 *
 * The operator binds tighter than every binary operator but looser than
 * COLLATE, so `-a COLLATE c` negates the collated value (MYSQL-PRECEDENCE-001).
 *
 * Rule: MYSQL-UNARY-001. Facts: unary plus has the facts of its operand
 * (the server drops it); unary minus follows MYSQL-NUMERIC-RESULT-001; `~`
 * is a bit operator; `!` is a truth value. Each is NULL when the operand
 * is, and takes a single value. Terminates: the operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/arithmetic-functions.html#operator_unary-minus,
 * https://dev.mysql.com/doc/refman/8.4/en/bit-functions.html#operator_bitwise-invert,
 * https://dev.mysql.com/doc/refman/8.4/en/logical-operators.html#operator_not.
 * Status: Implemented.
 *
 * @visibility public
 * @example Writing the tight negation
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE !a = 1')->toString() // => 'SELECT a FROM t WHERE ! a = 1'
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
        Check::input((new Precedence())->opening($operand) >= Precedence::PREFIX, 'The operand of a prefix operator needs a grouping to keep its place.');
    }

    /**
     * Derives the operand and the type of the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operands = new Operands();
        $fact = $operands->single($derivation->scalar($this->operand, $environment), $derivation);
        $numbers = new NumericResult();

        return match ($this->operator) {
            UnaryOperator::Plus => new ScalarFact($fact->type, $fact->nullability),
            UnaryOperator::Minus => new ScalarFact($numbers->negation($this->operand, $fact), $fact->nullability),
            UnaryOperator::Invert => new ScalarFact($numbers->bits([[$this->operand, $fact]], $derivation->context->profile->grammar), $fact->nullability),
            UnaryOperator::Not => $operands->truth($fact->nullability),
        };
    }

    /**
     * Writes the operator and the operand.
     */
    public function render(Output $out): void
    {
        $out->symbol($this->operator->value)->node($this->operand);
    }
}
