<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Window;

use MySqlMemory\Evaluation\Evaluable;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;

/**
 * One boundary of the frame of a window: UNBOUNDED, the current row, or an offset from it.
 *
 * In a ROWS frame an offset counts rows. In a RANGE frame it is a distance from the value of the
 * single ORDER BY expression of the window: the boundary lies at the value of the current row moved
 * by the offset, toward the rows before it for PRECEDING and after it for FOLLOWING, and a row whose
 * value is NULL has only its peers there.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-frames.html.
 *
 * @visibility MySqlMemory
 */
final class Bound
{
    /**
     * @param FrameBoundKind $kind The kind of boundary
     * @param int $rows The number of rows of an offset of a ROWS frame
     * @param Evaluable|null $limit The value an offset of a RANGE frame moves the ordering value of the current row to, computed over that row
     */
    public function __construct(public readonly FrameBoundKind $kind, public readonly int $rows = 0, public readonly ?Evaluable $limit = null)
    {
    }

    /**
     * Answers the row of the partition a boundary of a ROWS frame lies at: an index from -1, before the first row, to the number of rows, after the last.
     *
     * @param int $row The index of the current row
     * @param int $count The number of rows of the partition
     */
    public function rows(int $row, int $count): int
    {
        return match ($this->kind) {
            FrameBoundKind::UnboundedPreceding => 0,
            FrameBoundKind::Preceding => $this->rows > $row ? -1 : $row - $this->rows,
            FrameBoundKind::CurrentRow => $row,
            FrameBoundKind::Following => $this->rows >= $count - $row ? $count : $row + $this->rows,
            FrameBoundKind::UnboundedFollowing => $count - 1,
        };
    }
}
