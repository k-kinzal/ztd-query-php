<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Preparation;

use LogicException;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Scalar;

/**
 * Reads the first retained preparation condition in operand order.
 *
 * Ordinary scalars remain dependencies, while row operands retain a fixed
 * multi-column shape and deferred operands retain their owning condition.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DeferredArguments
{
    /**
     * @param non-empty-list<Scalar|RowOperand|DeferredOperand> $operands Dependencies in preparation order
     * @throws LogicException When no operand retains a deferred condition
     */
    public function problem(array $operands): Diagnostic
    {
        foreach ($operands as $operand) {
            if ($operand instanceof DeferredOperand) {
                return $operand->problem();
            }
            if ($operand instanceof RowOperand) {
                $parts = (new RowShapes())->parts($operand);
                \SqlSemantics\Diagnostic\Check::invariant($parts !== null && count($parts) > 1, 'A retained row operand has a fixed multi-column shape.');
                return new OperandColumns(1, count($parts));
            }
        }
        throw new LogicException('A deferred expression retains at least one preparation condition.');
    }
}
