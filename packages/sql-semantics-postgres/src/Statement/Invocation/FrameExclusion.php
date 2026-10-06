<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation;

/**
 * The rows a window frame leaves out around the current row.
 *
 * EXCLUDE NO OTHERS states the default and is the absence of an exclusion.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-WINDOW-FUNCTIONS.
 *
 * @visibility public
 * @example Spelling the exclusion of the current row's peers
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameExclusion::Group->value // => 'GROUP'
 */
enum FrameExclusion: string
{
    case CurrentRow = 'CURRENT ROW';
    case Group = 'GROUP';
    case Ties = 'TIES';
}
