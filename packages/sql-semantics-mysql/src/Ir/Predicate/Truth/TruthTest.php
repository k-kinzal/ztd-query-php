<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate\Truth;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A native integer test of one resolved truth operand.
 *
 * @visibility public
 * @example Inspecting a NULL test against the unknown truth class
 *     $test = new \SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthTest(\SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthOperator::Unknown, new \SqlSemantics\Platform\MySql\Ir\NullLiteral());
 *     $test->operator->value // => 'UNKNOWN'
 */
final class TruthTest
{
    use Snapshot;

    /**
     * Pairs one closed operand with the resolved native operation.
     */
    public function __construct(
        public readonly TruthOperator $operator,
        public readonly BooleanLiteral|ColumnReference|IntegralColumnReference|IntegerComparison|IntegerMembership|IntegerRange|LogicalPredicate|NullLiteral|NullTest|RangeIntegerLiteral|TextComparison|self $operand,
    ) {
    }
}
