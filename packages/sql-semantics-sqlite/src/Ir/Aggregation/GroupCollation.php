<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Aggregation;

/**
 * Identifies one of the standard SQLite text equivalence rules.
 *
 * @visibility public
 * @example Naming SQLite ASCII case-insensitive grouping
 *     \SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupCollation::NoCase->value // => 'NOCASE'
 */
enum GroupCollation: string
{
    case Binary = 'BINARY';
    case NoCase = 'NOCASE';
    case RightTrim = 'RTRIM';
}
