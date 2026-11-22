<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Window;

/**
 * The kinds of window frame boundary.
 *
 * Source: https://sqlite.org/windowfunctions.html#frame_boundaries.
 *
 * @visibility SqlSemantics
 */
enum FrameBoundKind
{
    case UnboundedPreceding;
    case Preceding;
    case CurrentRow;
    case Following;
    case UnboundedFollowing;
}
