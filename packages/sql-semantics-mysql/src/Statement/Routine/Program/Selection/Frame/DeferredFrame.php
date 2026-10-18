<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameExclusion;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Snapshot;

/**
 * A complete frame containing a non-scalar interval boundary.
 *
 * This role preserves the frame unit, both boundaries and exclusion. It is
 * retained by inactive queries and stored instructions without claiming that a
 * row is a scalar offset. Ordinary boundaries use Frame instead.
 *
 * @visibility public
 * @example Refusing to defer an ordinary frame
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrame(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Rows, new \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::CurrentRow)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredFrame implements Node
{
    use Snapshot;

    /**
     * @param FrameUnit $unit The unit
     * @param FrameBound|DeferredFrameBound $start The start boundary
     * @param FrameBound|DeferredFrameBound|null $end The end boundary of the BETWEEN form
     * @param FrameExclusion|null $exclusion The written exclusion
     */
    public function __construct(public readonly FrameUnit $unit, public readonly FrameBound|DeferredFrameBound $start, public readonly FrameBound|DeferredFrameBound|null $end = null, public readonly ?FrameExclusion $exclusion = null)
    {
        Check::input($start instanceof DeferredFrameBound || $end instanceof DeferredFrameBound, 'A deferred frame retains at least one non-scalar boundary.');
        Check::input($start->kind !== FrameBoundKind::UnboundedFollowing, 'A frame cannot start at UNBOUNDED FOLLOWING.');
        Check::input($end !== null || $start->kind !== FrameBoundKind::Following, 'A frame without BETWEEN cannot start FOLLOWING.');
        Check::input($end?->kind !== FrameBoundKind::UnboundedPreceding, 'A frame cannot end at UNBOUNDED PRECEDING.');
    }

    /**
     * Answers the offsets of the boundaries, in written order.
     *
     * @return list<Operand>
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
