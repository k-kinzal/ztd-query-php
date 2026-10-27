<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Lock;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `UNLOCK INSTANCE`: a request to release the backup lock of the session (MySQL 8.0 and later).
 *
 * Mirrors Sql_cmd_unlock_instance. Rule: MYSQL-UNLOCK-INSTANCE-001. The statement names no
 * relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-instance-for-backup.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('unlock instance')->toString() // => 'UNLOCK INSTANCE'
 */
final class UnlockInstance extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * Has nothing to derive: the request names no relation and no value.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('UNLOCK', 'INSTANCE');
    }
    /**
     * Constructs this SQL form with optional completed semantics and an input range.
     */
    public function __construct(?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
    }
}
