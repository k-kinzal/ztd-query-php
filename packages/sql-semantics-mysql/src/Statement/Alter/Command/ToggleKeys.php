<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `ENABLE KEYS` or `DISABLE KEYS`: a request to start or stop updating the nonunique indexes of a MyISAM table.
 *
 * Mirrors PT_alter_table_enable_keys.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-index.
 *
 * @visibility public
 * @example Stopping index updates
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t DISABLE KEYS')->statement->commands[0]->enable // => false
 */
final class ToggleKeys implements AlterCommand
{
    use Snapshot;

    /**
     * @param bool $enable Whether ENABLE (true) or DISABLE (false) is written
     */
    public function __construct(public readonly bool $enable)
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
        $out->keyword($this->enable ? 'ENABLE' : 'DISABLE', 'KEYS');
    }
}
