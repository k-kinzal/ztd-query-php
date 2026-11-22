<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Aggregation;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An ordered, named aggregate result or grouping key.
 *
 * @visibility public
 * @example Naming the first grouping key
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Aggregation\GroupColumn(new \SqlSemantics\Platform\PostgreSql\Ir\ColumnReference(0), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('key')))->name->value // => 'key'
 */
final class GroupColumn
{
    use Snapshot;

    /**
     * Retains the named group result role and its immutable value.
     */
    public function __construct(public readonly ColumnReference|Aggregate $value, public readonly Identifier $name)
    {
    }
}
