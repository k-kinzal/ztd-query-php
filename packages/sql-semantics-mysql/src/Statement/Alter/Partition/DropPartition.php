<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions;

/**
 * `DROP PARTITION p, …`: a request to remove partitions and their rows.
 *
 * Mirrors PT_alter_table_drop_partition.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table-partition-operations.html.
 *
 * @visibility SqlSemantics
 */
final class DropPartition implements StandaloneCommand
{
    use Snapshot;

    /**
     * @param NamedPartitions $partitions The partitions
     */
    public function __construct(public readonly NamedPartitions $partitions)
    {
    }

    /**
     * Derives nothing: the action holds no expression.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'PARTITION')->node($this->partitions);
    }
}
