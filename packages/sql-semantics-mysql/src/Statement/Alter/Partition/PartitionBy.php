<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;

/**
 * `PARTITION BY …` in ALTER TABLE: a request to partition or repartition the table.
 *
 * Mirrors PT_alter_table_partition_by. The partitioning is derived in the
 * scope of the changed table (MYSQL-PARTITIONING-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table-partition-operations.html.
 *
 * @visibility SqlSemantics
 */
final class PartitionBy implements TrailingCommand
{
    use Snapshot;

    /**
     * @param PartitionClause $partitioning The new partitioning
     */
    public function __construct(public readonly PartitionClause $partitioning)
    {
    }

    /**
     * Derives the partitioning in the scope of the table.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        $this->partitioning->derivePartitioning($derivation, $scope);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->node($this->partitioning);
    }
}
