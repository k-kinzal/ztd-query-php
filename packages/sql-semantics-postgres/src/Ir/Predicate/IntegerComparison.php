<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A Boolean comparison with two explicitly ordered integer operands.
 *
 * @visibility public
 * @example Testing a slot against a literal
 *     $test = new \SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison(\SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator::Less, new \SqlSemantics\Platform\PostgreSql\Ir\ColumnReference(0), new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('1'));
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
        public readonly ColumnReference|IntegerLiteral|WideIntegerColumnReference|WideIntegerLiteral $left,
        public readonly ColumnReference|IntegerLiteral|WideIntegerColumnReference|WideIntegerLiteral $right,
    ) {
    }
}
