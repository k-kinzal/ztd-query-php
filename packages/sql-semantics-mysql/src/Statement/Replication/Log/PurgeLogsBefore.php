<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Log;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `PURGE BINARY LOGS BEFORE datetime_expr`: deletes the binary log files older than a moment.
 *
 * Mirrors SQLCOM_PURGE_BEFORE with LEX::purge_value_list. The moment is an
 * expression the server evaluates as a DATETIME; it is derived in an
 * environment that sees no table. MASTER LOGS is a synonym of BINARY LOGS up
 * to 8.3 and is written BINARY LOGS. Rule: MYSQL-PURGE-002. Facts: the facts
 * of the expression.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/purge-binary-logs.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Purging the logs before a moment
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("purge binary logs before '2024-01-01 00:00:00'")->toString() // => "PURGE BINARY LOGS BEFORE '2024-01-01 00:00:00'"
 */
final class PurgeLogsBefore implements Statement
{
    use Snapshot;

    /**
     * @param Scalar $moment The moment: files last modified before it are deleted
     */
    public function __construct(public readonly Scalar $moment)
    {
    }

    /**
     * Derives the moment in an environment without tables.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->scalar($this->moment, $derivation->environment());
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('PURGE', 'BINARY', 'LOGS', 'BEFORE')->node($this->moment);
    }
}
