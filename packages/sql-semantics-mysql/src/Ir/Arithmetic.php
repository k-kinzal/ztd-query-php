<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * One MySQL numeric operation with ordered left and right operands.
 *
 * @visibility public
 * @example Preserving the left and right operand roles
 *     $left = new \SqlSemantics\Platform\MySql\Ir\IntegerLiteral('2');
 *     $right = new \SqlSemantics\Platform\MySql\Ir\IntegerLiteral('3');
 *     (new \SqlSemantics\Platform\MySql\Ir\Arithmetic(\SqlSemantics\Platform\MySql\Ir\ArithmeticOperator::Subtract, $left, $right))->right->decimal // => '3'
 */
final class Arithmetic
{
    use Snapshot;

    /**
     * Creates an operation whose operands have the given evaluation order.
     */
    public function __construct(
        public readonly ArithmeticOperator $operator,
        public readonly self|ColumnReference|IntegerLiteral|RangeIntegerLiteral $left,
        public readonly self|ColumnReference|IntegerLiteral|RangeIntegerLiteral $right,
    ) {
    }
}
