<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Literal;

/**
 * The numerical base of an integer's digits.
 * @visibility public
 * @example Reading the base of a hexadecimal integer
 *     \SqlSemantics\Statement\Literal\Radix::Hexadecimal->value // => 16
 */
enum Radix: int
{
    case Binary = 2;
    case Octal = 8;
    case Decimal = 10;
    case Hexadecimal = 16;
}
