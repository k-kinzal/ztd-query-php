<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

/**
 * Identifies one of the builtin SQLite text comparison rules.
 *
 * @visibility public
 * @example Naming SQLite ASCII case-insensitive grouping
 *     \SqlSemantics\Platform\Sqlite\Ir\Collation::NoCase->value // => 'NOCASE'
 */
enum Collation: string
{
    case Binary = 'BINARY';
    case NoCase = 'NOCASE';
    case RightTrim = 'RTRIM';
}
