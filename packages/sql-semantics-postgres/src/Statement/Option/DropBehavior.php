<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Option;

/**
 * Whether dependent objects are dropped too (CASCADE) or prevent the command (RESTRICT).
 *
 * A command that writes neither behaves as RESTRICT; the absence is kept as
 * null by the commands that hold this choice.
 * Source: https://www.postgresql.org/docs/17/sql-droptable.html.
 *
 * @visibility public
 * @example Spelling the cascading behavior
 *     \SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior::Cascade->value // => 'CASCADE'
 */
enum DropBehavior: string
{
    case Cascade = 'CASCADE';
    case Restrict = 'RESTRICT';
}
