<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A non-null Boolean decision about a checked predicate.
 *
 * @visibility public
 * @example Testing a NULL result
 *     $test = new \SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTest(\SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthOperator::IsUnknown, new \SqlSemantics\Platform\PostgreSql\Ir\NullLiteral());
 *     $test->operator->value // => 'IS UNKNOWN'
 */
final class TruthTest
{
    use Snapshot;

    /**
     * Owns one checked Boolean or NULL operand.
     */
    public function __construct(
        public readonly TruthOperator $operator,
        public readonly BooleanColumnReference|BooleanLiteral|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|LogicalPredicate|NullLiteral|NullTest|TextComparison|self $operand,
    ) {
    }
}
