<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation;

/**
 * Where a window frame starts or ends.
 *
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-WINDOW-FUNCTIONS.
 *
 * @visibility public
 * @example Telling which bounds take an offset
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBoundKind::OffsetPreceding->offset() // => true
 */
enum FrameBoundKind: string
{
    case UnboundedPreceding = 'UNBOUNDED PRECEDING';
    case OffsetPreceding = 'PRECEDING';
    case CurrentRow = 'CURRENT ROW';
    case OffsetFollowing = 'FOLLOWING';
    case UnboundedFollowing = 'UNBOUNDED FOLLOWING';

    /**
     * Tells whether the bound is an offset expression before PRECEDING or FOLLOWING.
     */
    public function offset(): bool
    {
        return $this === self::OffsetPreceding || $this === self::OffsetFollowing;
    }
}
