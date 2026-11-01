<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Window;

use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;

/**
 * The frame of a window: the rows of the partition an aggregate, FIRST_VALUE, LAST_VALUE or NTH_VALUE reads for each row.
 *
 * A window without a frame clause reads, with ORDER BY, the rows from the start of the partition
 * to the last peer of the current row, and without it the whole partition. The ranking functions,
 * LEAD and LAG read the partition and ignore the frame.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-frames.html.
 *
 * @visibility MySqlMemory
 */
final class WindowFrame
{
    /**
     * @param FrameUnit $unit Whether the offsets count rows or values
     * @param Bound $start The first row of the frame
     * @param Bound $end The last row of the frame
     */
    public function __construct(public readonly FrameUnit $unit, public readonly Bound $start, public readonly Bound $end)
    {
    }

    /**
     * Answers the frame of a window without a frame clause.
     *
     * @param bool $ordered Whether the window has ORDER BY
     */
    public static function default(bool $ordered): self
    {
        return new self(FrameUnit::Range, new Bound(FrameBoundKind::UnboundedPreceding), new Bound($ordered ? FrameBoundKind::CurrentRow : FrameBoundKind::UnboundedFollowing));
    }
}
