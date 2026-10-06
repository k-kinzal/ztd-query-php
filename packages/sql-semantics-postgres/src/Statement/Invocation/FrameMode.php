<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation;

/**
 * How a window frame measures its bounds: in rows, in peer groups, or in a value range.
 *
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-WINDOW-FUNCTIONS.
 *
 * @visibility public
 * @example Spelling the peer-group mode
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameMode::Groups->value // => 'GROUPS'
 */
enum FrameMode: string
{
    case Range = 'RANGE';
    case Rows = 'ROWS';
    case Groups = 'GROUPS';
}
