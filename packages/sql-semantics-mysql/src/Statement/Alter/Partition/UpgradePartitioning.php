<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `UPGRADE PARTITIONING` (5.7): a request to convert a table to native InnoDB partitioning.
 *
 * Source: https://dev.mysql.com/doc/refman/5.7/en/alter-table-partition-operations.html.
 *
 * @visibility public
 * @example Upgrading the partitioning of a 5.7 table
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t UPGRADE PARTITIONING')->toString() // => 'ALTER TABLE t UPGRADE PARTITIONING'
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
