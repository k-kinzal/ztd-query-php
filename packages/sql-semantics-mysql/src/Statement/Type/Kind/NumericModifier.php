<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Kind;

/**
 * The attributes written after a numeric type.
 *
 * Each case holds the keywords the type is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/numeric-type-attributes.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier::Unsigned->value // => 'UNSIGNED'
 */
enum NumericModifier: string
{
    case Signed = 'SIGNED';
    case Unsigned = 'UNSIGNED';
    case Zerofill = 'ZEROFILL';
}
