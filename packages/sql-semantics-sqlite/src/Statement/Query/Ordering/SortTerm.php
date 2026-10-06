<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Ordering;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One ordering term: an expression with its optional direction and NULL placement.
 *
 * The same term form orders query results, window partitions and aggregate
 * arguments, and lists the columns of an index or of a conflict target.
 *
 * @visibility public
 * @example Reading an ordering term
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t ORDER BY a + 1 ASC NULLS FIRST');
 *     $term = $query->statement->orderBy[0];
 *     [$term->direction, $term->nulls] // => [\SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection::Ascending, \SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\NullsOrder::First]
 */
final class SortTerm implements Node
{
    use Snapshot;

    /**
     * @param Scalar $expression The ordering expression
     * @param SortDirection|null $direction The written direction
     * @param NullsOrder|null $nulls The written NULL placement
     */
    public function __construct(public readonly Scalar $expression, public readonly ?SortDirection $direction = null, public readonly ?NullsOrder $nulls = null)
    {
    }

    /**
     * Writes the expression, the direction and the NULL placement.
     */
    public function render(Output $out): void
    {
        $out->node($this->expression);
        if ($this->direction !== null) {
            $out->keyword($this->direction->value);
        }
        if ($this->nulls !== null) {
            $out->keyword('NULLS', $this->nulls->value);
        }
    }
}
