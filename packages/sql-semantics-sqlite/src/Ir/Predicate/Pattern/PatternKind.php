<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern;

/**
 * The two built-in pattern operations in the fixed SQLite profile.
 *
 * @visibility public
 * @example Inspecting the native operator for a case-sensitive pattern
 *     \SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternKind::Glob->value // => 'GLOB'
 */
enum PatternKind: string
{
    case AsciiLike = 'LIKE';
    case Glob = 'GLOB';
}
