<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Log;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `PURGE BINARY LOGS TO 'file'`: deletes the binary log files listed before the named one.
 *
 * Mirrors SQLCOM_PURGE with LEX::to_log. MASTER LOGS is a synonym of BINARY
 * LOGS up to 8.3; the selected word is retained. Rule: MYSQL-PURGE-001. The
 * statement has no facts of its own.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/purge-binary-logs.html,
 * https://dev.mysql.com/doc/refman/5.7/en/purge-binary-logs.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Preserving the MASTER LOGS synonym
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze("purge master logs to 'binlog.000010'")->toString() // => "PURGE MASTER LOGS TO 'binlog.000010'"
 */
final class PurgeLogsTo extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Text $file The name of the first binary log file kept
     * @param bool $masterLogs Whether the request uses the legacy MASTER synonym
     */
    public function __construct(public readonly Text $file, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $masterLogs = false)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        \SqlSemantics\Diagnostic\Check::input($file->radix === null, 'A binary log file name is quoted text.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
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
        $out->keyword('PURGE', $this->masterLogs ? 'MASTER' : 'BINARY', 'LOGS', 'TO')->node($this->file);
    }
}
