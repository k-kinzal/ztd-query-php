<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * `REMOVE PARTITIONING`: a request to turn a partitioned table into an unpartitioned one.
 *
 * Mirrors PT_alter_table_remove_partitioning.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table-partition-operations.html.
 *
 * @visibility SqlSemantics
 */
final class RemovePartitioning implements TrailingCommand
{
    use Snapshot;

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
        $out->keyword('REMOVE', 'PARTITIONING');
    }
}
