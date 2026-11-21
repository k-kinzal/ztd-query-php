<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One SQLite numeric operation with ordered left and right operands.
 *
 * @visibility public
 * @example Preserving the left and right operand roles
 *     $left = new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('2');
 *     $right = new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('3');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Arithmetic(\SqlSemantics\Platform\Sqlite\Ir\ArithmeticOperator::Subtract, $left, $right))->right->decimal // => '3'
 */
final class Arithmetic
{
    use Snapshot;

    /**
     * Creates an operation whose operands have the given evaluation order.
     */
    public function __construct(
        public readonly ArithmeticOperator $operator,
        public readonly self|BlobLiteral|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral $left,
        public readonly self|BlobLiteral|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral $right,
    ) {
    }
}
