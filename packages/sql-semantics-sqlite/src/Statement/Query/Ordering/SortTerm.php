<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Ordering;

use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One ordering term: an expression with its optional direction and NULL placement.
 *
 * The same term form orders query results, window partitions and aggregate
 * arguments, and lists the columns of an index or of a conflict target.
 *
 * @visibility SqlSemantics
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
