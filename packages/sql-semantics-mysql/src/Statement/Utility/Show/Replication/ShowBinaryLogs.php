<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;

/**
 * SHOW BINARY LOGS (SHOW MASTER LOGS): the binary log files of the server.
 *
 * Rule: MYSQL-SHOW-BINARY-LOGS-001. MASTER and BINARY are the same keyword here (UtilityNoise); the
 * writer emits BINARY, which every release accepts. MySQL 8.0 added the
 * `Encrypted` column. The columns are those of the layout of
 * MYSQL-SHOW-ROWS-001. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-binary-logs.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ShowBinaryLogs implements Statement
{
    use Snapshot;


    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->rows($derivation, Report::BinaryLogs);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'BINARY', 'LOGS');
    }
}
