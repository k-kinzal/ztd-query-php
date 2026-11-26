<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Aggregation;

use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One named count or a key read from the group representative.
 *
 * @visibility public
 * @example Naming a counted result
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupColumn(new \SqlSemantics\Platform\Sqlite\Ir\Aggregation\Count(), new \SqlSemantics\Platform\Sqlite\Ir\Identifier('n')))->name->value // => 'n'
 */
final class GroupColumn
{
    use Snapshot;

    /**
     * Keeps the result role and the observed label.
     */
    public function __construct(public readonly ColumnReference|Count $value, public readonly Identifier $name)
    {
    }
}
