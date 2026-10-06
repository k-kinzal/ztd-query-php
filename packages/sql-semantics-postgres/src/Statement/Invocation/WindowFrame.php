<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * The frame of a window: its mode, its start, an optional end, and an optional exclusion.
 *
 * A frame written with a start only ends at the current row. The combinations
 * the grammar rejects cannot be constructed: a frame does not start at
 * UNBOUNDED FOLLOWING or end at UNBOUNDED PRECEDING, a frame that starts at
 * the current row does not end before it, and a frame that starts after the
 * current row does not end at or before it.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-WINDOW-FUNCTIONS.
 *
 * @visibility public
 * @example Reading a frame with a start only
 *     $frame = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowFrame(
 *         \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameMode::Rows,
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBound(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBoundKind::UnboundedPreceding),
 *     );
 *     [$frame->mode->value, $frame->end] // => ['ROWS', null]
 * @example Rejecting a frame that starts at UNBOUNDED FOLLOWING
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowFrame(
 *         \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameMode::Rows,
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBound(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBoundKind::UnboundedFollowing),
 *     ) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class WindowFrame implements Clause
{
    use Snapshot;

    /**
     * @param FrameMode $mode How the bounds are measured
     * @param FrameBound $start Where the frame starts
     * @param FrameBound|null $end Where the frame ends; null when only a start is written
     * @param FrameExclusion|null $exclusion The rows left out around the current row
     */
    public function __construct(
        public readonly FrameMode $mode,
        public readonly FrameBound $start,
        public readonly ?FrameBound $end = null,
        public readonly ?FrameExclusion $exclusion = null,
    ) {
        $last = $end === null ? FrameBoundKind::CurrentRow : $end->kind;
        Check::input($start->kind !== FrameBoundKind::UnboundedFollowing, 'A frame does not start at UNBOUNDED FOLLOWING.');
        Check::input($last !== FrameBoundKind::UnboundedPreceding, 'A frame does not end at UNBOUNDED PRECEDING.');
        Check::input($start->kind !== FrameBoundKind::CurrentRow || $last !== FrameBoundKind::OffsetPreceding, 'A frame starting at the current row does not end before it.');
        Check::input($start->kind !== FrameBoundKind::OffsetFollowing || ($last !== FrameBoundKind::OffsetPreceding && $last !== FrameBoundKind::CurrentRow), 'A frame starting after the current row does not end at or before it.');
    }

    /**
     * Derives the offset expressions of the bounds.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->start->deriveClause($derivation, $environment);
        $this->end?->deriveClause($derivation, $environment);
    }

    /**
     * Writes the mode, the bounds and the exclusion.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->mode->value);
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
