<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;

/**
 * `FORCE`: a request to rebuild the table even when nothing else changes.
 *
 * Mirrors PT_alter_table_force.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-performance.
 *
 * @visibility SqlSemantics
 */
final class Force implements AlterCommand
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
        $out->keyword('FORCE');
    }
}
