<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Statement;

use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionStatement;

/**
 * Reads the intrinsic conditions of transaction control before execution.
 *
 * The concrete name operand owns the notice. This stage neither resolves
 * transaction state nor reads the request label or an analysis index.
 *
 * @visibility MySqlMemory
 */
final class TransactionNotices
{
    /**
     * Reports syntax notices from the owning transaction operands during reading.
     * Preparation calls this once; replay and stored-program execution do not.
     */
    public function read(TransactionStatement $statement, Session $session): void
    {
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Savepoint || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\ReleaseSavepoint || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\RollbackToSavepoint) {
            $warning = $statement->nameWarning;
            if ($warning !== null) {
                $session->diagnostics->warning($warning->code(), $warning->message());
            }
        }
    }

}
