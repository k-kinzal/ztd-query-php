<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A resolved logical composition of two-valued column tests in a partial B-tree.
 *
 * @visibility public
 * @example Negating a test of the first physical column
 *     $leaf = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\NullIndexPredicate(0);
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexBooleanPredicate(\SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalOperator::Not, $leaf))->left->slot // => 0
 */
final class IndexBooleanPredicate
{
    use Snapshot;

    /**
     * Fixes the operator and the ordered operands according to its arity.
     */
    public function __construct(
        public readonly LogicalOperator $operator,
        public readonly NullIndexPredicate|IndexTruthTest|self $left,
        public readonly NullIndexPredicate|IndexTruthTest|self|null $right = null,
    ) {
        Check::input(($operator === LogicalOperator::Not) === ($right === null), 'A partial index NOT needs one operand and AND or OR needs two.');
    }
}
