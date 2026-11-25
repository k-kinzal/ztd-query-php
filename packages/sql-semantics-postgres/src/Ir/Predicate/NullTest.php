<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A scalar null test whose result always has a known truth value.
 *
 * @visibility public
 * @example Testing whether a table slot is present
 *     $test = new \SqlSemantics\Platform\PostgreSql\Ir\Predicate\NullTest(new \SqlSemantics\Platform\PostgreSql\Ir\ColumnReference(0), true);
 *     $test->negated // => true
 */
final class NullTest
{
    use Snapshot;

    /**
     * Stores the tested scalar and whether to invert the result.
     */
    public function __construct(
        public readonly BooleanColumnReference|BooleanLiteral|ColumnReference|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|IntegerLiteral|LogicalPredicate|NullLiteral|TextColumnReference|TextComparison|TruthTest|WideIntegerColumnReference|self $operand,
        public readonly bool $negated = false,
    ) {
    }
}
