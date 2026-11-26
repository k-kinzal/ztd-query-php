<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Aggregation;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An ordered, named aggregate result or grouping key.
 *
 * @visibility public
 * @example Naming the first grouping key
 *     (new \SqlSemantics\Platform\MySql\Ir\Aggregation\GroupColumn(new \SqlSemantics\Platform\MySql\Ir\ColumnReference(0), new \SqlSemantics\Platform\MySql\Ir\Identifier('key')))->name->value // => 'key'
 */
final class GroupColumn
{
    use Snapshot;

    /**
     * Retains the named group result role and its immutable value.
     */
    public function __construct(public readonly ColumnReference|Aggregate $value, public readonly Identifier $name)
    {
        Check::input($value instanceof ColumnReference || $name->value === ltrim($name->value, " \t\r\n\v\f"), 'A MySQL grouped result name cannot start with SQL whitespace.');
    }
}
