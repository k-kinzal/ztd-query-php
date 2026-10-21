<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An arithmetic operator receives a geometry domain instead of a numeric operand.
 *
 * The condition precedes value evaluation, including NULL geometry values.
 * Binary casts remove the geometry domain. Legacy bit operators accept it.
 * Verified against native MySQL 5.7 and 8.4 text and prepared statements.
 *
 * @visibility public
 * @example Describing a spatial arithmetic operand
 *     (new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\SpatialArithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus))->message() // => 'Incorrect arguments to +.'
 */
final class SpatialArithmetic implements Diagnostic
{
    use Snapshot;

    /**
     * @param ArithmeticOperator|UnaryOperator $operator The arithmetic or bit operator that refuses geometry
     */
    public function __construct(public readonly ArithmeticOperator|UnaryOperator $operator)
    {
        Check::input($operator instanceof ArithmeticOperator || in_array($operator, [UnaryOperator::Minus, UnaryOperator::Invert], true), 'Only numeric negation and bit inversion refuse unary geometry operands.');
    }

    /**
     * Describes the operator's argument constraint.
     */
    public function message(): string
    {
        return 'Incorrect arguments to ' . $this->operator->value . '.';
    }
}
