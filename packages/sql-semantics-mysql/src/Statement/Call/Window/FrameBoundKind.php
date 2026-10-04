<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Window;

/**
 * The kinds of boundary of a window frame.
 *
 * Each case holds the keywords written after the offset, if any.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-frames.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::UnboundedFollowing->value // => 'UNBOUNDED FOLLOWING'
 */
enum FrameBoundKind: string
{
    case UnboundedPreceding = 'UNBOUNDED PRECEDING';
    case Preceding = 'PRECEDING';
    case CurrentRow = 'CURRENT ROW';
    case Following = 'FOLLOWING';
    case UnboundedFollowing = 'UNBOUNDED FOLLOWING';

    /**
     * Tells whether the boundary has an offset.
     */
    public function offset(): bool
    {
        return $this === self::Preceding || $this === self::Following;
    }
}
