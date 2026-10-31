<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml\Read;

use SqlSemantics\Platform\MySql\Statement\Dml\Problem\Read\OrderingPosition;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem;
use SqlSemantics\Statement\Operand;

/**
 * Identifies output positions in writes that have no select list.
 *
 * Parentheses and unary plus preserve a bare integer's positional role. Other
 * numeric expressions are ordinary ordering values. Catalog and row changes
 * cannot give DELETE or UPDATE an output position to which an integer binds.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Ordering
{
    /**
     * @param list<OrderItem|DeferredOrderItem> $items The complete ordering clause
     */
    public function problem(array $items): ?OrderingPosition
    {
        foreach ($items as $item) {
            $problem = $this->position($item->expression);
            if ($problem !== null) {
                return $problem;
            }
        }
        return null;
    }

    /**
     * Retains the unsigned integer without narrowing it to a PHP integer.
     */
    public function position(Operand $operand, bool $legacyBoolean = false): ?OrderingPosition
    {
        while ($operand instanceof Grouped || ($operand instanceof Unary && $operand->operator === UnaryOperator::Plus)) {
            $operand = $operand->operand;
        }
        if ($legacyBoolean && $operand instanceof BooleanLiteral) {
            return new OrderingPosition($operand);
        }
        return $operand instanceof NumberLiteral && $operand->form === NumberForm::Integer ? new OrderingPosition($operand) : null;
    }
}
