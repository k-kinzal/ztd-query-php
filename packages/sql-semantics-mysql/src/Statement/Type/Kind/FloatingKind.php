<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Kind;

/**
 * The approximate numeric types of MySQL; REAL is DOUBLE unless the session sets REAL_AS_FLOAT.
 *
 * Each case holds the keywords the type is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/floating-point-types.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind::Double->value // => 'DOUBLE'
 */
enum FloatingKind: string
{
    case Float = 'FLOAT';
    case Real = 'REAL';
    case Double = 'DOUBLE';
}
