<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Resolved;

/**
 * How strongly the collation of a string holds against the collation of another string it meets.
 *
 * The lower value wins; two different collations of the same value conflict.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-collation-coercibility.html.
 *
 * @visibility public
 * @example Reading the value COERCIBILITY() returns for a literal
 *     \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible->value // => 4
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
