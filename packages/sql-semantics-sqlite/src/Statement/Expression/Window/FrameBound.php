<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Window;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One boundary of a window frame.
 *
 * @visibility public
 * @example Reading the offset of a frame boundary
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT sum(a) OVER (ROWS 2 PRECEDING) FROM t');
 *     $query->statement->columns[0]->expression->over->frame->start->offset->digits // => '2'
 * @example Refusing an offset on a boundary that takes none
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBound(\SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind::CurrentRow, new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an offset on unbounded preceding
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBound(\SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind::UnboundedPreceding, new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing preceding without an offset
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBound(\SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind::Preceding) // throws \SqlSemantics\Diagnostic\InvalidConstruction
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
