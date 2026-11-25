<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A binary join with an explicitly scoped predicate and ordered input occurrences.
 *
 * @visibility public
 * @example Reading the leaves of a self join
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $value = $semantics->analyze('SELECT a.id FROM app.accounts a CROSS JOIN app.accounts b', $semantics->context(['CREATE TABLE app.accounts (id INTEGER)']));
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
        public readonly BooleanLiteral|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|LogicalPredicate|NullLiteral|NullTest|null $on = null,
    ) {
        $inputs = $this->inputs();
        Check::input(count($inputs) <= 61, 'A joined source can have at most 61 input occurrences.');
        Check::input(($kind === JoinKind::Cross) === ($on === null), 'Only CROSS has no join predicate.');
        (new OccurrenceScope())->predicate($on, $inputs);
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
