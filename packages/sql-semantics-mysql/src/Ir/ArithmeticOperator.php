<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

/**
 * MySQL binary arithmetic operations available in a complete projection.
 *
 * @visibility public
 * @example Choosing a subtraction
 *     \SqlSemantics\Platform\MySql\Ir\ArithmeticOperator::Subtract->value // => '-'
 */
enum ArithmeticOperator: string
{
    case Add = '+';
    case Subtract = '-';
    case Multiply = '*';
    case IntegerDivide = 'DIV';
    case Remainder = '%';
}
