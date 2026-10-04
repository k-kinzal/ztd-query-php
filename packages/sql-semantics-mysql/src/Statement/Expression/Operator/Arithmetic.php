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
use SqlSemantics\Statement\Type\Nullability;

/**
 * A binary arithmetic or bit operation of the bit_expr level, in the order written (`Item_func_plus`, `Item_func_int_div`, `Item_func_bit_or`, …).
 *
 * The operators associate to the left with the levels of
 * MYSQL-PRECEDENCE-001; an operand that would be read differently without a
 * grouping is rejected, among them a right operand of `+` or `-` that
 * starts with a leading interval `INTERVAL n unit + x`, which the parser
 * reads as a trailing interval.
 *
 * Rule: MYSQL-ARITHMETIC-001. Facts: the type follows
 * MYSQL-NUMERIC-RESULT-001. `+`, `-`, `*` and the bit operators are NULL
 * when an operand is; `/`, DIV and `%` are also NULL for a zero divisor.
 * Both operands take a single value. Terminates: the operands are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/arithmetic-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/bit-functions.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Writing MOD as the remainder operator
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a MOD 2 = 1')->toString() // => 'SELECT a FROM t WHERE a % 2 = 1'
 * @example Typing a division of integers as DECIMAL
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE 1 / 2');
 *     $query->facts->scalar($query->statement->where)->type->descriptor->name() // => 'DECIMAL'
 */
final class Arithmetic implements Scalar
{
    use Snapshot;

    /**
     * @param ArithmeticOperator $operator The operator
     * @param Scalar $left The left operand
     * @param Scalar $right The right operand
     */
    public function __construct(public readonly ArithmeticOperator $operator, public readonly Scalar $left, public readonly Scalar $right)
    {
        $precedence = new Precedence();
        Check::input($precedence->fits($left, $operator->level(), $operator->level()), 'The left operand of ' . $operator->value . ' needs a grouping to keep its place.');
        Check::input($precedence->opening($right) > $operator->level(), 'The right operand of ' . $operator->value . ' needs a grouping to keep its place.');
        Check::input(!$precedence->leading($right) instanceof IntervalAddition || ($operator !== ArithmeticOperator::Plus && $operator !== ArithmeticOperator::Minus), 'A leading interval after ' . $operator->value . ' is read as a trailing interval and needs a grouping.');
    }

    /**
     * Derives both operands and the type of the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operands = new Operands();
        $left = $operands->single($derivation->scalar($this->left, $environment), $derivation);
        $right = $operands->single($derivation->scalar($this->right, $environment), $derivation);
        $type = (new NumericResult())->binary($this->operator, $this->left, $left, $this->right, $right, $derivation->context->profile->grammar);
        $divides = $this->operator === ArithmeticOperator::Divide || $this->operator === ArithmeticOperator::IntegerDivide || $this->operator === ArithmeticOperator::Modulo;

        return new ScalarFact($type, $divides ? Nullability::Nullable : $left->nullability->propagate($right->nullability));
    }

    /**
     * Writes the operands around the operator.
     */
    public function render(Output $out): void
    {
        $out->node($this->left);
        if ($this->operator === ArithmeticOperator::IntegerDivide) {
            $out->keyword('DIV');
        } else {
            $out->symbol($this->operator->value);
        }
        $out->node($this->right);
    }
}
