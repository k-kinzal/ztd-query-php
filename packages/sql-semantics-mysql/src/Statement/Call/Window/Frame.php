<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Window;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The frame of a window: its unit, its boundaries and its exclusion.
 *
 * The short form names the start only, which is not FOLLOWING, and ends at
 * the current row; the BETWEEN form names both ends. UNBOUNDED FOLLOWING
 * cannot start a frame and UNBOUNDED PRECEDING cannot end one.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-frames.html.
 *
 * @visibility public
 * @example Reading both ends of a frame
 *     $frame = new \SqlSemantics\Platform\MySql\Statement\Call\Window\Frame(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Rows, new \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::UnboundedPreceding), new \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::CurrentRow));
 *     $frame->end?->kind // => \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::CurrentRow
 * @example Refusing a start the grammar does not admit
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Window\Frame(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Rows, new \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::UnboundedFollowing)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Frame implements Node
{
    use Snapshot;

    /**
     * @param FrameUnit $unit The unit
     * @param FrameBound $start The start boundary
     * @param FrameBound|null $end The end boundary of the BETWEEN form
     * @param FrameExclusion|null $exclusion The written exclusion
     */
    public function __construct(public readonly FrameUnit $unit, public readonly FrameBound $start, public readonly ?FrameBound $end = null, public readonly ?FrameExclusion $exclusion = null)
    {
        Check::input($start->kind !== FrameBoundKind::UnboundedFollowing, 'A frame cannot start at UNBOUNDED FOLLOWING.');
        Check::input($end !== null || $start->kind !== FrameBoundKind::Following, 'A frame without BETWEEN cannot start FOLLOWING.');
        Check::input($end?->kind !== FrameBoundKind::UnboundedPreceding, 'A frame cannot end at UNBOUNDED PRECEDING.');
    }

    /**
     * Answers the offsets of the boundaries, in written order.
     *
     * @return list<Scalar>
     */
    public function offsets(): array
    {
        $offsets = [];
        foreach ([$this->start->offset, $this->end?->offset] as $offset) {
            if ($offset !== null) {
                $offsets[] = $offset;
            }
        }

        return $offsets;
    }

    /**
     * Writes the frame.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->unit->value);
        if ($this->end === null) {
            $out->node($this->start);
        } else {
            $out->keyword('BETWEEN')->node($this->start)->keyword('AND')->node($this->end);
        }
        if ($this->exclusion !== null) {
            $out->keyword('EXCLUDE', ...explode(' ', $this->exclusion->value));
        }
    }
}
