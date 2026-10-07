<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

/**
 * How strongly the collation of a string value holds when it meets a value of another collation.
 *
 * The lower level wins: an explicit COLLATE over a column, a column over a literal. Two values
 * of one level and different collations of one character set make the binary collation win
 * when one is binary; otherwise their mix is an error.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-collation-coercibility.html.
 *
 * @visibility public
 * @example A column wins over a literal
 *     \MySqlMemory\Typing\Coercibility::Implicit->value < \MySqlMemory\Typing\Coercibility::Coercible->value // => true
 */
enum Coercibility: int
{
    case Explicit = 0;
    case None = 1;
    case Implicit = 2;
    case SystemConstant = 3;
    case Coercible = 4;
    case Numeric = 5;
    case Ignorable = 6;
}
