<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Aggregation;

use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One ordered comparison against the count of a formed group.
 *
 * @visibility public
 * @example Keeping groups with more than one row
 *     $query = (new \SqlSemantics\Platform\Sqlite\Semantics())->analyze('SELECT count(*) AS n HAVING count(*) > 1');
 *     $query->having->threshold->decimal // => '1'
 */
final class CountComparison
{
    use Snapshot;

    /**
     * Stores the aggregate, operator, threshold, and their operand order.
     */
    public function __construct(
        public readonly Count $count,
        public readonly ComparisonOperator $operator,
        public readonly IntegerLiteral|NullLiteral|Parameter $threshold,
        public readonly bool $thresholdFirst = false,
    ) {
    }
}
