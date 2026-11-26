<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Aggregation;

use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A group input slot with an explicit builtin collation.
 *
 * @visibility public
 * @example Constructing a binary group key
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupKey(new \SqlSemantics\Platform\Sqlite\Ir\ColumnReference(0)))->collation->value // => 'BINARY'
 */
final class GroupKey
{
    use Snapshot;

    /**
     * Constrains the key to the one supported grouping input occurrence.
     */
    public function __construct(
        public readonly ColumnReference $column,
        public readonly Collation $collation = Collation::Binary,
    ) {
        Check::input($column->source === 0, 'A grouping key must read the single input occurrence.');
    }
}
