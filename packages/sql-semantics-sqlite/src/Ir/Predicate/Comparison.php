<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Predicate;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One ordered comparison in an ON or WHERE predicate.
 *
 * @visibility public
 * @example Constructing a predicate
 *     $predicate = new \SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison(\SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator::Equal, new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('1'), new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('1'));
 *     $predicate->left->decimal // => '1'
 */
final class Comparison
{
    use Snapshot;

    /**
     * Holds the operator with its left and right operands.
     */
    public function __construct(
        public readonly ComparisonOperator $operator,
        public readonly Arithmetic|ColumnReference|IntegerLiteral|NullLiteral|Parameter $left,
        public readonly Arithmetic|ColumnReference|IntegerLiteral|NullLiteral|Parameter $right,
    ) {
    }
}
