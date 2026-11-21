<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Window;

/**
 * The rows a window frame leaves out.
 *
 * Source: https://sqlite.org/windowfunctions.html#the_exclude_clause.
 *
 * @visibility public
 * @example Reading the exclusion of a frame
 *     $query = (new \SqlSemantics\Platform\Sqlite\Semantics())->inspect('SELECT sum(a) OVER (ROWS 1 PRECEDING EXCLUDE TIES) FROM t');
 *     $query->statement->columns[0]->expression->over->frame->exclusion // => \SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameExclusion::Ties
 */
enum FrameExclusion: string
{
    case NoOthers = 'NO OTHERS';
    case CurrentRow = 'CURRENT ROW';
    case Group = 'GROUP';
    case Ties = 'TIES';
}
