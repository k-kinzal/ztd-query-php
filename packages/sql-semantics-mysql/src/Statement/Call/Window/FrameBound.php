<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Window;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One boundary of a window frame: UNBOUNDED, CURRENT ROW, or an offset PRECEDING or FOLLOWING.
 *
 * An offset is a number, a parameter marker, or `INTERVAL expr unit` for a
 * RANGE over a temporal ordering. The window that holds the boundary
 * derives the offset.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-frames.html.
 *
 * @visibility public
 * @example Reading the offset of a frame boundary
 *     $bound = new \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::Preceding, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'));
 *     $bound->offset->text // => '2'
 * @example Refusing an offset on a boundary that takes none
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::CurrentRow, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class FrameBound implements Node
{
    use Snapshot;

    /**
     * @param FrameBoundKind $kind The kind of boundary
     * @param Scalar|null $offset The distance from the current row; present exactly for PRECEDING and FOLLOWING
     * @param IntervalUnit|null $unit The unit of an INTERVAL offset
     */
    public function __construct(public readonly FrameBoundKind $kind, public readonly ?Scalar $offset = null, public readonly ?IntervalUnit $unit = null)
    {
        Check::input(($offset !== null) === $kind->offset(), 'A frame boundary has an offset exactly when it is PRECEDING or FOLLOWING.');
        Check::input($offset === null || $unit !== null || $offset instanceof NumberLiteral || $offset instanceof Parameter, 'A frame offset is a number, a parameter marker or an interval.');
    }

    /**
     * Writes the boundary.
     */
    public function render(Output $out): void
    {
        if ($this->unit !== null) {
            $out->keyword('INTERVAL')->node($this->offset)->keyword($this->unit->value);
        } else {
            $out->node($this->offset);
        }
        $out->keyword(...explode(' ', $this->kind->value));
    }
}
