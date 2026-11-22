<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Window;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The frame of a window: its unit, its boundaries and its exclusion.
 *
 * The short form names the start only and ends at the current row; the
 * BETWEEN form names both ends. The grammar admits UNBOUNDED PRECEDING only
 * as a start and UNBOUNDED FOLLOWING only as an end.
 * Source: https://sqlite.org/windowfunctions.html#frame_specifications.
 *
 * @visibility SqlSemantics
 * @example Refusing a start the grammar does not admit
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Window\Frame(\SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameUnit::Rows, new \SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBound(\SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind::UnboundedFollowing)) // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 * @example Refusing an end at unbounded preceding
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Window\Frame(\SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameUnit::Rows, new \SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBound(\SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind::CurrentRow), new \SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBound(\SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind::UnboundedPreceding)) // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
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
        Check::input($end?->kind !== FrameBoundKind::UnboundedPreceding, 'A frame cannot end at UNBOUNDED PRECEDING.');
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
