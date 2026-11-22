<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One further arm of a compound query with the operator before it.
 *
 * @visibility SqlSemantics
 */
final class CompoundStep implements Node
{
    use Snapshot;

    /**
     * @param CompoundOperator $operator The operator before the arm
     * @param Select|ValuesClause $query The arm
     */
    public function __construct(public readonly CompoundOperator $operator, public readonly Select|ValuesClause $query)
    {
    }

    /**
     * Writes the operator and the arm.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->operator->value))->node($this->query);
    }
}
