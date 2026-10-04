<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `DROP PARTITION p, …`: a request to remove partitions and their rows.
 *
 * Mirrors PT_alter_table_drop_partition.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table-partition-operations.html.
 *
 * @visibility public
 * @example Dropping two partitions
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t DROP PARTITION p0, p1');
 *     count($alter->statement->commands[0]->partitions->names) // => 2
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
