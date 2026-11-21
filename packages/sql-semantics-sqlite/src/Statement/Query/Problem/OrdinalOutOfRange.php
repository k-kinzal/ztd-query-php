<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Problem;

use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\Resolution;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * An ORDER BY or GROUP BY integer that names no result column.
 *
 * @visibility public
 * @example Reading the problem of an ordinal beyond the result columns
 *     (new \SqlSemantics\Platform\Sqlite\Semantics())->inspect('SELECT 1 ORDER BY 2') // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\SemanticException: Term 2 is out of range - should be between 1 and 1.
 */
final class OrdinalOutOfRange implements Resolution, Diagnostic
{
    use Snapshot;

    /**
     * @param int $ordinal The integer written
     * @param int $columns The number of result columns
     */
    public function __construct(public readonly int $ordinal, public readonly int $columns)
    {
    }

    /**
     * Describes the range the ordinal must lie in.
     */
    public function message(): string
    {
        return 'Term ' . $this->ordinal . ' is out of range - should be between 1 and ' . $this->columns . '.';
    }
}
