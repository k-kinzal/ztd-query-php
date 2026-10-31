<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Log;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;

/**
 * `PURGE BINARY LOGS BEFORE datetime_expr`: deletes the binary log files older than a moment.
 *
 * Mirrors SQLCOM_PURGE_BEFORE with LEX::purge_value_list. The moment is an
 * query-free expression the server evaluates as a DATETIME; it is derived in an
 * environment that sees no table. MASTER LOGS is a synonym of BINARY LOGS up
 * to 8.3; the selected word is retained. Rule: MYSQL-PURGE-002. Facts: the facts
 * of the expression.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/purge-binary-logs.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Purging the logs before a moment
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("purge binary logs before '2024-01-01 00:00:00'")->toString() // => "PURGE BINARY LOGS BEFORE '2024-01-01 00:00:00'"
 */
final class PurgeLogsBefore extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Scalar $moment The moment: files last modified before it are deleted
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the moment contains a subquery
     * @param bool $masterLogs Whether the request uses the legacy MASTER synonym
     */
    public function __construct(public readonly Scalar $moment, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $masterLogs = false)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        (new \SqlSemantics\Platform\MySql\Rules\Replication\LogMoment())->check($moment);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the moment in an environment without tables.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new \SqlSemantics\Platform\MySql\Rules\Call\Arguments())->one($this->moment, $derivation, $derivation->environment());
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('PURGE', $this->masterLogs ? 'MASTER' : 'BINARY', 'LOGS', 'BEFORE')->node($this->moment);
    }
}
