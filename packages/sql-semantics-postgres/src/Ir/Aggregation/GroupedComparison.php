<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Aggregation;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A signed comparison of values in the owning PostgreSQL group.
 *
 * @visibility public
 * @example Comparing a row count against a threshold
 *     $count = new \SqlSemantics\Platform\PostgreSql\Ir\Aggregation\Aggregate(\SqlSemantics\Platform\PostgreSql\Ir\Aggregation\AggregateOperator::Count);
 *     $comparison = new \SqlSemantics\Platform\PostgreSql\Ir\Aggregation\GroupedComparison(\SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator::Greater, $count, new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('2'));
 *     $comparison->operator->value // => '>'
 */
final class GroupedComparison
{
    use Snapshot;

    /**
     * Establishes the operator and the exact ordered group operands.
     */
    public function __construct(
        public readonly IntegerOperator $operator,
        public readonly Aggregate|ColumnReference|IntegerLiteral|WideIntegerLiteral $left,
        public readonly Aggregate|ColumnReference|IntegerLiteral|WideIntegerLiteral $right,
    ) {
    }
}
