<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Lock;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `LOCK INSTANCE FOR BACKUP`: a request to take the backup lock of the server instance (MySQL 8.0 and later).
 *
 * Mirrors Sql_cmd_lock_instance. Rule: MYSQL-LOCK-INSTANCE-001. The statement names no
 * relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-instance-for-backup.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('lock instance for backup')->toString() // => 'LOCK INSTANCE FOR BACKUP'
 */
final class LockInstance extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
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
        $out->keyword('LOCK', 'INSTANCE', 'FOR', 'BACKUP');
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
