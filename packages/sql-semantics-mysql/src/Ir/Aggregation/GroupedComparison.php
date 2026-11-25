<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Aggregation;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A signed comparison of values in the owning MySQL group.
 *
 * @visibility public
 * @example Comparing a row count against a threshold
 *     $count = new \SqlSemantics\Platform\MySql\Ir\Aggregation\Aggregate(\SqlSemantics\Platform\MySql\Ir\Aggregation\AggregateOperator::Count);
 *     $comparison = new \SqlSemantics\Platform\MySql\Ir\Aggregation\GroupedComparison(\SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator::Greater, $count, new \SqlSemantics\Platform\MySql\Ir\IntegerLiteral('2'));
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
        public readonly Aggregate|ColumnReference|IntegerLiteral|RangeIntegerLiteral $left,
        public readonly Aggregate|ColumnReference|IntegerLiteral|RangeIntegerLiteral $right,
    ) {
        Check::input($operator !== IntegerOperator::NullSafeEqual, 'HAVING needs a supported numeric comparison operator.');
        foreach ([$left, $right] as $operand) {
            Check::input(!$operand instanceof Aggregate || !$operand->argument instanceof TextColumnReference
                || $operand->operator === AggregateOperator::Count, 'A numeric HAVING comparison needs a numeric aggregate result.');
        }
    }
}
