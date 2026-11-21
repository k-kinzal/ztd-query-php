<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Predicate;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A logical operation with ordered predicate operands.
 *
 * @visibility public
 * @example Constructing a predicate
 *     $predicate = new \SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical(\SqlSemantics\Platform\Sqlite\Ir\Predicate\LogicalOperator::And, new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('1'), new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('1'));
 *     $predicate->left->decimal // => '1'
 */
final class Logical
{
    use Snapshot;

    /**
     * Holds the operator with its left and right operands.
     */
    public function __construct(
        public readonly LogicalOperator $operator,
        public readonly Arithmetic|BlobLiteral|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|self|NullLiteral|Parameter|RealLiteral|TextLiteral $left,
        public readonly Arithmetic|BlobLiteral|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|self|NullLiteral|Parameter|RealLiteral|TextLiteral $right,
    ) {
    }
}
