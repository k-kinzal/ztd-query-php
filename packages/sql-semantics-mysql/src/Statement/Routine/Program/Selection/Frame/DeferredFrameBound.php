<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * An interval boundary retaining a non-scalar distance until its window is reached.
 *
 * A stored instruction or unused common table can retain this boundary. It is
 * never an ordinary scalar boundary. Both its operand and unit remain available
 * when the enclosing frame and its dependencies are prepared.
 *
 * @visibility public
 * @example Refusing an interval on CURRENT ROW
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()]);
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrameBound(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::CurrentRow, $row, \SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredFrameBound implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param FrameBoundKind $kind PRECEDING or FOLLOWING
     * @param RowOperand|DeferredOperand $offset The complete non-scalar interval operand
     * @param IntervalUnit $unit The written interval unit
     */
    public function __construct(public readonly FrameBoundKind $kind, public readonly RowOperand|DeferredOperand $offset, public readonly IntervalUnit $unit, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($kind->offset(), 'A deferred interval boundary is PRECEDING or FOLLOWING.');
    }

    /**
     * Writes the complete interval without replacing its operand.
     */
    public function render(Output $out): void
    {
        $out->keyword('INTERVAL')->node($this->offset)->keyword($this->unit->value)->keyword(...explode(' ', $this->kind->value));
    }
}
