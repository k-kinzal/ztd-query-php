<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Transaction;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\XaState;
use Override;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\ReleaseSavepoint;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\RollbackToSavepoint;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Savepoint;
use SqlSemantics\Statement\Operation;

/**
 * Executes SAVEPOINT, ROLLBACK TO SAVEPOINT and RELEASE SAVEPOINT.
 *
 * A savepoint the transaction lacks is ER_SP_DOES_NOT_EXIST, naming it as the statement wrote it.
 * The statements fail with XAER_RMFAIL while an XA transaction is idle.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/savepoint.html.
 *
 * @visibility MySqlMemory
 */
final class SavepointCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Sets, restores or deletes the savepoint.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $transaction = $session->transaction;
        if ($transaction->xa === XaState::Idle) {
            throw ErrorCode::XaWrongState->error($transaction->xa->value);
        }
        if ($statement instanceof Savepoint) {
            $transaction->savepoint($statement->savepoint->value);
        } elseif ($statement instanceof RollbackToSavepoint) {
            $transaction->rollbackTo($statement->savepoint->value);
        } elseif ($statement instanceof ReleaseSavepoint) {
            $transaction->release($statement->savepoint->value);
        }

        return new Completion();
    }
}
