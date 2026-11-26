<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

/**
 * PostgreSQL binary arithmetic operations available in a complete projection.
 *
 * @visibility public
 * @example Choosing a subtraction
 *     \SqlSemantics\Platform\PostgreSql\Ir\ArithmeticOperator::Subtract->value // => '-'
 */
enum ArithmeticOperator: string
{
    case Add = '+';
    case Subtract = '-';
    case Multiply = '*';
    case Divide = '/';
    case Remainder = '%';
    case BitAnd = '&';
    case BitOr = '|';
    case BitXor = '#';
}
