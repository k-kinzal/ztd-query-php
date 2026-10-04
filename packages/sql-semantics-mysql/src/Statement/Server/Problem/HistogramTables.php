<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A histogram request of ANALYZE TABLE that names more than one table.
 *
 * The server builds or drops histograms for one table only and reports an
 * error instead.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html.
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Problem\HistogramTables(2))->message() // => 'A histogram request names one table, not 2.'
 */
final class HistogramTables implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $count The number of tables the statement names
     */
    public function __construct(public readonly int $count)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'A histogram request names one table, not ' . $this->count . '.';
    }
}
