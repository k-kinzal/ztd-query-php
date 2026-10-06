<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

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
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SHOW BINARY LOGS');
 *     [$show->field(1)->name?->value, $show->toString()] // => ['File_size', 'SHOW BINARY LOGS']
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
