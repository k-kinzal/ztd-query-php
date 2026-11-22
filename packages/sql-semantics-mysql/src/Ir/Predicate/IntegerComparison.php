<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A Boolean comparison with two explicitly ordered integer operands.
 *
 * @visibility public
 * @example Testing a slot against a literal
 *     $test = new \SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison(\SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator::Less, new \SqlSemantics\Platform\MySql\Ir\ColumnReference(0), new \SqlSemantics\Platform\MySql\Ir\IntegerLiteral('1'));
 *     $test->right->decimal // => '1'
 */
final class IntegerComparison
{
    use Snapshot;

    /**
     * Stores the operator and both operands exactly once.
     */
    public function __construct(
        public readonly IntegerOperator $operator,
        public readonly ColumnReference|IntegerLiteral $left,
        public readonly ColumnReference|IntegerLiteral $right,
    ) {
    }
}
