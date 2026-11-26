<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthTest;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A typed truth expression with the arity required by its operator.
 *
 * @visibility public
 * @example Negating UNKNOWN
 *     $truth = new \SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate(\SqlSemantics\Platform\MySql\Ir\Predicate\LogicalOperator::Not, new \SqlSemantics\Platform\MySql\Ir\NullLiteral());
 *     $truth->right // => null
 */
final class LogicalPredicate
{
    use Snapshot;

    /**
     * Closes the operator over one or two predicates.
     */
    public function __construct(
        public readonly LogicalOperator $operator,
        public readonly BooleanLiteral|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|NullLiteral|NullTest|TruthTest|self $left,
        public readonly BooleanLiteral|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|NullLiteral|NullTest|TruthTest|self|null $right = null,
    ) {
        Check::input(($operator === LogicalOperator::Not) === ($right === null), 'Only logical NOT has one operand.');
    }
}
