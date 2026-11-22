<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Window;

/**
 * The rows a window frame leaves out.
 *
 * Source: https://sqlite.org/windowfunctions.html#the_exclude_clause.
 *
 * @visibility SqlSemantics
 */
enum FrameExclusion: string
{
    case NoOthers = 'NO OTHERS';
    case CurrentRow = 'CURRENT ROW';
    case Group = 'GROUP';
    case Ties = 'TIES';
}
