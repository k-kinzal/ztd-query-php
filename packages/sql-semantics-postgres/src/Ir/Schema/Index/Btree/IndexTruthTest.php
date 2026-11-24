<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthOperator;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A native truth test of a resolved Boolean column in a partial B-tree.
 *
 * @visibility public
 * @example Testing whether the second physical column is unknown
 *     $test = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexTruthTest(1, \SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthOperator::IsUnknown);
 *     $test->operator->value // => 'IS UNKNOWN'
 */
final class IndexTruthTest
{
    use Snapshot;

    /**
     * Fixes the physical slot and the truth operation.
     */
    public function __construct(public readonly int $slot, public readonly TruthOperator $operator)
    {
        Check::input($slot >= 0, 'An index truth test needs a nonnegative column slot.');
    }
}
