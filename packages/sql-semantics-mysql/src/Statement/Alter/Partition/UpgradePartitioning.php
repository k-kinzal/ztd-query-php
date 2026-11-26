<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;

/**
 * `UPGRADE PARTITIONING` (5.7): a request to convert a table to native InnoDB partitioning.
 *
 * Source: https://dev.mysql.com/doc/refman/5.7/en/alter-table-partition-operations.html.
 *
 * @visibility SqlSemantics
 */
final class UpgradePartitioning implements AlterCommand
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
        $out->keyword('UPGRADE', 'PARTITIONING');
    }
}
