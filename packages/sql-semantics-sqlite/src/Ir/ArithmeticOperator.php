<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

/**
 * SQLite binary numeric operations available in a checked scalar expression.
 *
 * @visibility public
 * @example Choosing a subtraction
 *     \SqlSemantics\Platform\Sqlite\Ir\ArithmeticOperator::Subtract->value // => '-'
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
    case ShiftLeft = '<<';
    case ShiftRight = '>>';
}
