<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused;

/**
 * A statement that is only a partitioning clause, `PARTITION BY …` (5.6 and 5.7).
 *
 * Rule: MYSQL-PARTITION-ENTRY-001. The grammar of 5.6 and 5.7 has this
 * statement so that the server can read the partitioning stored in a table
 * definition file; sent by a client it fails with ER_PARTITION_ENTRY_ERROR,
 * which is the diagnostic PartitionEntryRefused. No table is partitioned, so
 * the clause is derived in a scope with no visible relation. The statement
 * changes and provides no declaration.
 * Source: https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/sql_yacc.yy (rule partition_entry).
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class PartitionEntry implements Statement
{
    use Snapshot;

    /**
     * @param PartitionClause $partitioning The partitioning clause
     */
    public function __construct(public readonly PartitionClause $partitioning)
    {
    }

    /**
     * Reports the refusal and derives the clause without a table.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->report(new PartitionEntryRefused());
        $this->partitioning->derivePartitioning($derivation, $derivation->environment());
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->node($this->partitioning);
    }
}
