<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A typed truth expression with the arity required by its operator.
 *
 * @visibility public
 * @example Negating UNKNOWN
 *     $truth = new \SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate(\SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalOperator::Not, new \SqlSemantics\Platform\PostgreSql\Ir\NullLiteral());
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
        public readonly BooleanColumnReference|BooleanLiteral|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|NullLiteral|NullTest|TextComparison|TruthTest|self $left,
        public readonly BooleanColumnReference|BooleanLiteral|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|NullLiteral|NullTest|TextComparison|TruthTest|self|null $right = null,
    ) {
        Check::input(($operator === LogicalOperator::Not) === ($right === null), 'Only logical NOT has one operand.');
    }
}
