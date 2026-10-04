<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

/**
 * The arithmetic a value takes part in: signed or unsigned integer, exact decimal, or double precision.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/arithmetic-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/type-conversion.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
enum NumericClass
{
    case Signed;
    case Unsigned;
    case Decimal;
    case Double;
}
