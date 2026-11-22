<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Group;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;

/**
 * `STOP GROUP_REPLICATION`: stops group replication (MySQL 5.7 and later).
 *
 * Mirrors SQLCOM_STOP_GROUP_REPLICATION. Rule: MYSQL-GROUP-REPLICATION-002.
 * The statement has no facts of its own.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/stop-group-replication.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class StopGroupReplication implements Statement
{
    use Snapshot;

    /**
     * Checks that the release has group replication.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        Check::input($derivation->context->profile->grammar !== GrammarRelease::MySql5651, 'Group replication needs MySQL 5.7 or later.');
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('STOP', 'GROUP_REPLICATION');
    }
}
