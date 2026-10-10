<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Problem;

/**
 * A bound violated by a declared numeric or temporal type.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/numeric-type-syntax.html,
 * https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html.
 *
 * @visibility public
 * @example Naming a bound
 *     \SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::Precision->value // => 'precision'
 */
enum TypeLimit: string
{
    case Width = 'width';
    case Precision = 'precision';
    case Scale = 'scale';
    case ScaleExceedsPrecision = 'scale-exceeds-precision';
    case Empty = 'empty';
    case Specifier = 'specifier';
}
