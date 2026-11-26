<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Log;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * `PURGE BINARY LOGS TO 'file'`: deletes the binary log files listed before the named one.
 *
 * Mirrors SQLCOM_PURGE with LEX::to_log. MASTER LOGS is a synonym of BINARY
 * LOGS up to 8.3 and is written BINARY LOGS. Rule: MYSQL-PURGE-001. The
 * statement has no facts of its own.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/purge-binary-logs.html,
 * https://dev.mysql.com/doc/refman/5.7/en/purge-binary-logs.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class PurgeLogsTo implements Statement
{
    use Snapshot;

    /**
     * @param Text $file The name of the first binary log file kept
     */
    public function __construct(public readonly Text $file)
    {
    }

    /**
     * Records nothing: the statement names a file, not a declaration.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('PURGE', 'BINARY', 'LOGS', 'TO')->node($this->file);
    }
}
