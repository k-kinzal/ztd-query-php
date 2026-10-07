<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A PARTITION clause on a table that is not partitioned (`ER_PARTITION_CLAUSE_ON_NONPARTITIONED`, error 1747).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-selection.html.
 *
 * @visibility public
 * @example Selecting a partition of a plain table
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $table = $semantics->analyze('CREATE TABLE t (a INT)');
 *     $semantics->analyze('SELECT a FROM t PARTITION (p0)', [$table])->facts->diagnostics[0]->message() // => 'PARTITION () clause on non partitioned table'
 */
final class UnpartitionedTable implements Diagnostic
{
    use Snapshot;

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return 'PARTITION () clause on non partitioned table';
    }
}
