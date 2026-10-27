<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Write;

use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * A stored column assignment retaining a non-scalar value until its write is prepared.
 *
 * The destination and operand remain separate dependencies. Ordinary Assignment
 * continues to accept only scalar values or DEFAULT.
 *
 * @visibility public
 * @example Retaining a duplicate-key assignment in a skipped stored instruction
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO t VALUES (1) ON DUPLICATE KEY UPDATE a = ROW(1, 2)');
 *     $operation->body->onDuplicate[0] instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment // => true
 */
final class DeferredColumnAssignment implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param ColumnUse $column The complete assignment target
     * @param RowOperand|DeferredOperand $value The non-scalar operand in a scalar assignment position
     */
    public function __construct(public readonly ColumnUse $column, public readonly RowOperand|DeferredOperand $value, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }

    /**
     * Writes the target and the retained operand without substitutions.
     */
    public function render(Output $out): void
    {
        $out->node($this->column)->symbol('=')->node($this->value);
    }
}
