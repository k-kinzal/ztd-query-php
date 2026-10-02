<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

/**
 * The two digit systems of a binary string literal: hexadecimal and bit values.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/hexadecimal-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/bit-value-literals.html.
 *
 * @visibility public
 * @example Naming the digit system of a literal
 *     (new \SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Bit, '101'))->radix // => \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Bit
 */
enum Radix
{
    case Hexadecimal;
    case Bit;
}
