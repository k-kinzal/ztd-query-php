<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\From;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A binary join with an explicitly scoped predicate and ordered input occurrences.
 *
 * @visibility public
 * @example Reading the leaves of a self join
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $value = $semantics->analyze('SELECT a.id FROM public.accounts a CROSS JOIN public.accounts b', $semantics->context(['CREATE TABLE public.accounts (id INTEGER)']));
 *     count($value->source->inputs()) // => 2
 */
final class Join
{
    use Snapshot;

    /**
     * Checks the join operation and closes any predicate over its local inputs.
     */
    public function __construct(
        public readonly JoinKind $kind,
        public readonly TableSource|DerivedSource|self $left,
        public readonly TableSource|DerivedSource|self $right,
        public readonly BooleanLiteral|IntegerComparison|IntegerDistinctness|IntegerRange|LogicalPredicate|NullLiteral|NullTest|TruthTest|null $on = null,
    ) {
        $inputs = $this->inputs();
        Check::input(count($inputs) <= 61, 'A joined source can have at most 61 input occurrences.');
        Check::input(($kind === JoinKind::Cross) === ($on === null), 'Only CROSS has no join predicate.');
        (new OccurrenceScope())->predicate($on, $inputs);
        if ($kind === JoinKind::Full) {
            $leftWidth = $left instanceof self ? count($left->inputs()) : 1;
            Check::input(
                $on instanceof IntegerComparison && $on->operator === IntegerOperator::Equal
                    && $on->left instanceof ColumnReference && $on->right instanceof ColumnReference
                    && ($on->left->occurrence < $leftWidth) !== ($on->right->occurrence < $leftWidth),
                'A PostgreSQL FULL join requires integer equality between opposite inputs.',
            );
        }
    }

    /**
     * Returns the input occurrences in lexical order.
     * @return non-empty-list<TableSource|DerivedSource>
     */
    public function inputs(): array
    {
        $left = $this->left instanceof self ? $this->left->inputs() : [$this->left];
        $right = $this->right instanceof self ? $this->right->inputs() : [$this->right];

        return array_merge($left, $right);
    }
}
