<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

/**
 * SQLite binary arithmetic operations available in a complete projection.
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
}
