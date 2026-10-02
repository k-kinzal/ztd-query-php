<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Snapshot;

/**
 * An ORDER BY or GROUP BY integer that names no result column.
 *
 * @visibility public
 * @example Reading the problem of an ordinal beyond the result columns
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 ORDER BY 2');
 *     [$query->facts->diagnostics[0]->ordinal, $query->facts->diagnostics[0]->columns] // => [2, 1]
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
