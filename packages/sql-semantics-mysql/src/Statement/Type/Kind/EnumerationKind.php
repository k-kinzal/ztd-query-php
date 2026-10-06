<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Kind;

/**
 * The two types declared with a list of permitted values.
 *
 * Each case holds the keywords the type is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/enum.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind::Set->value // => 'SET'
 */
enum EnumerationKind: string
{
    case Enum = 'ENUM';
    case Set = 'SET';
}
