<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Window;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One boundary of a window frame.
 *
 * @visibility SqlSemantics
 * @example Refusing an offset on a boundary that takes none
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBound(\SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind::CurrentRow, new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1')) // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 * @example Refusing an offset on unbounded preceding
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBound(\SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind::UnboundedPreceding, new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1')) // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 * @example Refusing preceding without an offset
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBound(\SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind::Preceding) // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 */
final class FrameBound implements Node
{
    use Snapshot;

    /**
     * @param FrameBoundKind $kind The kind of boundary
     * @param Scalar|null $offset The distance from the current row; present exactly for PRECEDING and FOLLOWING with an expression
     */
    public function __construct(public readonly FrameBoundKind $kind, public readonly ?Scalar $offset = null)
    {
        Check::input(($offset !== null) === ($kind === FrameBoundKind::Preceding || $kind === FrameBoundKind::Following), 'A frame boundary has an offset exactly when it is an expression PRECEDING or FOLLOWING.');
    }

    /**
     * Writes the boundary.
     */
    public function render(Output $out): void
    {
        $out->node($this->offset);
        match ($this->kind) {
            FrameBoundKind::UnboundedPreceding => $out->keyword('UNBOUNDED', 'PRECEDING'),
            FrameBoundKind::Preceding => $out->keyword('PRECEDING'),
            FrameBoundKind::CurrentRow => $out->keyword('CURRENT', 'ROW'),
            FrameBoundKind::Following => $out->keyword('FOLLOWING'),
            FrameBoundKind::UnboundedFollowing => $out->keyword('UNBOUNDED', 'FOLLOWING'),
        };
    }
}
