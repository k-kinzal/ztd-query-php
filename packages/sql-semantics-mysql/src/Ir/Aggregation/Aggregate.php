<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Aggregation;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A native group aggregate over one input slot, or the row count.
 *
 * @visibility public
 * @example Counting admitted rows
 *     (new \SqlSemantics\Platform\MySql\Ir\Aggregation\Aggregate(\SqlSemantics\Platform\MySql\Ir\Aggregation\AggregateOperator::Count))->argument // => null
 */
final class Aggregate
{
    use Snapshot;

    /**
     * Establishes the operator's argument role before it enters a group.
     */
    public function __construct(
        public readonly AggregateOperator $operator,
        public readonly ColumnReference|TextColumnReference|null $argument = null,
        public readonly bool $distinct = false,
    ) {
        Check::input(
            $argument !== null || ($operator === AggregateOperator::Count && !$distinct),
            'Only a nondistinct COUNT can omit the aggregate argument.'
        );
        Check::input($argument === null || $argument->occurrence === 0, 'The aggregate argument must read its single input occurrence.');
        Check::input(!$argument instanceof TextColumnReference || $operator !== AggregateOperator::Sum, 'SUM requires a signed INT argument.');
    }
}
