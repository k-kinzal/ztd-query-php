<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A scalar null test whose result always has a known truth value.
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
     * Stores the tested scalar and whether to invert the result.
     */
    public function __construct(
        public readonly BooleanLiteral|ColumnReference|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|IntegerLiteral|LogicalPredicate|NullLiteral|TextColumnReference|self $operand,
        public readonly bool $negated = false,
    ) {
    }
}
