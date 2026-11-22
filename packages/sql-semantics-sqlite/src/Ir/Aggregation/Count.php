<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Aggregation;

use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A builtin COUNT of rows, nonnull input cells or distinct input values.
 *
 * @visibility public
 * @example Counting admitted rows
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Aggregation\Count())->argument // => null
 */
final class Count
{
    use Snapshot;

    /**
     * Restricts DISTINCT to a present argument from the single input.
     */
    public function __construct(
        public readonly ?ColumnReference $argument = null,
        public readonly ?Collation $distinctCollation = null,
    ) {
        Check::input($argument !== null || $distinctCollation === null, 'A distinct count requires a field argument.');
        Check::input($argument === null || $argument->source === 0, 'A count argument must read the single input occurrence.');
    }
}
