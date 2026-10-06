<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Log;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

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
 * @visibility public
 * @example Writing MASTER LOGS as its synonym
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze("purge master logs to 'binlog.000010'")->toString() // => "PURGE BINARY LOGS TO 'binlog.000010'"
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
