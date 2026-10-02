<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Window;

/**
 * The kinds of window frame boundary.
 *
 * Source: https://sqlite.org/windowfunctions.html#frame_boundaries.
 *
 * @visibility public
 * @example Reading the kind of a frame boundary
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT sum(a) OVER (ROWS UNBOUNDED PRECEDING) FROM t');
 *     $query->statement->columns[0]->expression->over->frame->start->kind // => \SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind::UnboundedPreceding
 */
enum FrameBoundKind
{
    case UnboundedPreceding;
    case Preceding;
    case CurrentRow;
    case Following;
    case UnboundedFollowing;
}
