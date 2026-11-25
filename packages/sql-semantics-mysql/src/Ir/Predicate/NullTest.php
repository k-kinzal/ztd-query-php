<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthTest;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A scalar null test in the statement's grammar release, with an always-known result.
 *
 * @visibility public
 * @example Testing whether a table slot is present
 *     $test = new \SqlSemantics\Platform\MySql\Ir\Predicate\NullTest(new \SqlSemantics\Platform\MySql\Ir\ColumnReference(0), true);
 *     $test->negated // => true
 */
final class NullTest
{
    use Snapshot;

    /**
     * Stores the scalar and selects the native IS NULL or IS NOT NULL operation.
     */
    public function __construct(
        public readonly BooleanLiteral|ColumnReference|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|IntegerLiteral|LogicalPredicate|NullLiteral|TextColumnReference|TruthTest|self $operand,
        public readonly bool $negated = false,
    ) {
    }
}
