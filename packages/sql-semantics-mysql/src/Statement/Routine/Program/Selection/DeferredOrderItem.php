<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection;

use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * A stored ordering or grouping item whose operand needs invocation-time preparation.
 *
 * The item preserves its written direction and complete operand instead of
 * presenting a row or deferred use as a scalar expression.
 *
 * @visibility public
 * @example Keeping a row-valued ordering item
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()]);
 *     $item = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem($row);
 *     $item->expression === $row // => true
 */
final class DeferredOrderItem implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param RowOperand|DeferredOperand $expression The ordering expression
     * @param Direction|null $direction The direction, when written
     */
    public function __construct(public readonly RowOperand|DeferredOperand $expression, public readonly ?Direction $direction = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }

    /**
     * Writes the expression and the direction.
     */
    public function render(Output $out): void
    {
        $out->node($this->expression);
        if ($this->direction !== null) {
            $out->keyword($this->direction->value);
        }
    }
}
