<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A partitioning clause sent as a statement, which the server of 5.6 and 5.7 rejects with ER_PARTITION_ENTRY_ERROR.
 *
 * Source: https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/sql_yacc.yy (rule partition_entry).
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused())->message() // => 'A partitioning clause is not a statement a client can send.'
 */
final class PartitionEntryRefused implements Diagnostic
{
    use Snapshot;

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'A partitioning clause is not a statement a client can send.';
    }
}
