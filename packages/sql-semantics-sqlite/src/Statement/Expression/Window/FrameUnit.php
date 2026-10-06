<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Window;

/**
 * The unit a window frame is measured in.
 *
 * Source: https://sqlite.org/windowfunctions.html#frame_specifications.
 *
 * @visibility public
 * @example Reading the unit of a frame
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT sum(a) OVER (ROWS 1 PRECEDING) FROM t');
 *     $query->statement->columns[0]->expression->over->frame->unit // => \SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameUnit::Rows
 */
enum FrameUnit: string
{
    case Range = 'RANGE';
    case Rows = 'ROWS';
    case Groups = 'GROUPS';
}
