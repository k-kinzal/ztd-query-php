<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows\Recursive;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\CommonTable;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;

/**
 * Closes each recursive and consuming occurrence over the same declared working relation.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RecursiveBinding
{
    /**
     * Confirms the source identity and its single working slot.
     */
    public function source(BoundStatement $operation, Select $query, CommonTableExpression $definition): TableReference
    {
        $source = $query->from;
        Check::input(
            $source instanceof TableReference && $source->partitions === [] && $source->indexHints === [] && $source->sample === null,
            'A recursive series requires one ordinary reference to its working relation.'
        );
        $fact = $operation->facts->relation($source);
        Check::input(
            $fact->table instanceof CommonTable && $fact->table->definition === $definition && count($fact->shape->slots) === 1,
            'A recursive source must resolve to its own one-column definition.'
        );

        return $source;
    }

    /**
     * Requires a direct read of the only working slot in this occurrence.
     */
    public function read(BoundStatement $operation, Scalar $expression, TableReference $source): void
    {
        $resolution = $operation->facts->scalar($expression)->resolution;
        $slot = $operation->facts->relation($source)->shape->slots[0] ?? null;
        Check::input($slot !== null && $resolution instanceof ResolvedColumn && $resolution->depth === 0
            && $resolution->relation === $source && $resolution->slot === $slot, 'A recursive reference must read the resolved working slot.');
    }
}
