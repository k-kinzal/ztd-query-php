<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * One PostgreSQL numeric operation with ordered left and right operands.
 *
 * @visibility public
 * @example Preserving the left and right operand roles
 *     $left = new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('2');
 *     $right = new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('3');
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Arithmetic(\SqlSemantics\Platform\PostgreSql\Ir\ArithmeticOperator::Subtract, $left, $right))->right->decimal // => '3'
 */
final class Arithmetic
{
    use Snapshot;

    /**
     * Creates an operation whose operands have the given evaluation order.
     */
    public function __construct(
        public readonly ArithmeticOperator $operator,
        public readonly self|ColumnReference|IntegerCase|IntegerLiteral $left,
        public readonly self|ColumnReference|IntegerCase|IntegerLiteral $right,
    ) {
    }
}
