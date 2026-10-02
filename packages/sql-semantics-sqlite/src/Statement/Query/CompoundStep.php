<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One further arm of a compound query with the operator before it.
 *
 * @visibility public
 * @example Reading a further arm
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 EXCEPT SELECT 2');
 *     $query->statement->steps[0]->query->columns[0]->expression->digits // => '2'
 */
final class CompoundStep implements Node
{
    use Snapshot;

    /**
     * @param CompoundOperator $operator The operator before the arm
     * @param Select|Values $query The arm
     */
    public function __construct(public readonly CompoundOperator $operator, public readonly Select|Values $query)
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
