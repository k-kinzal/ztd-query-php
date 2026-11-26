<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;

/**
 * `ROLLBACK [WORK] TO [SAVEPOINT] name`: a request to undo the changes made after a savepoint.
 *
 * Mirrors SQLCOM_ROLLBACK_TO_SAVEPOINT. Rule: MYSQL-ROLLBACK-TO-SAVEPOINT-001. The savepoint is a name of the
 * session's current transaction, which no declaration context holds; the
 * server compares savepoint names case-insensitively. WORK and the word SAVEPOINT are optional; WORK is not written. The statement
 * names no relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/savepoint.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class RollbackToSavepoint implements Statement
{
    use Snapshot;

    /**
     * @param Name $savepoint The savepoint name
     */
    public function __construct(public readonly Name $savepoint)
    {
    }

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
        $out->keyword('ROLLBACK', 'TO', 'SAVEPOINT')->name($this->savepoint, NameUse::Identifier);
    }
}
