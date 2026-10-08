<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Error\Family\TransactionError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Commit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Rollback;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\StartTransaction;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionCharacteristic;
use SqlSemantics\Statement\Operation;

/**
 * Executes BEGIN, START TRANSACTION, COMMIT and ROLLBACK.
 *
 * BEGIN and START TRANSACTION commit the open transaction and open one with the access mode and
 * the snapshot START TRANSACTION names. ROLLBACK warns with ER_WARNING_NOT_COMPLETE_ROLLBACK when
 * the transaction changed a table that is not transactional, before the warnings about temporary
 * tables. AND CHAIN opens a transaction with the isolation level and access mode of the one that
 * ended.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/commit.html.
 *
 * @visibility MySqlMemory
 */
final class TransactionCommand implements Command
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
     * Opens or ends the transaction of the session.
     *
     * @throws \MySqlMemory\Error\SqlError When an XA transaction is active or idle
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $transaction = $session->transaction;
        $characteristics = [$transaction->isolation, $transaction->readOnly];
        if ($statement instanceof Commit) {
            $transaction->commit();
        } elseif ($statement instanceof Rollback) {
            $kept = $transaction->temporaries;
            $unrestorable = $transaction->nontransactional;
            $transaction->rollback();
            if ($unrestorable) {
                $context->diagnostics->warning(TransactionError::NotCompleteRollback, TransactionError::NotCompleteRollback->message());
            }
            if (isset($kept['created'])) {
                $context->diagnostics->warning(1751, 'The creation of some temporary tables could not be rolled back.');
            }
            if (isset($kept['dropped'])) {
                $context->diagnostics->warning(1752, 'Some temporary tables were dropped, but these operations could not be rolled back.');
            }
        } else {
            $transaction->guard();
            $transaction->end();
            $characteristics = $statement instanceof StartTransaction ? $statement->characteristics : [];
            $transaction->begin(
                in_array(TransactionCharacteristic::ReadOnly, $characteristics, true) ? true : (in_array(TransactionCharacteristic::ReadWrite, $characteristics, true) ? false : null),
                in_array(TransactionCharacteristic::WithConsistentSnapshot, $characteristics, true),
            );

            return new Completion(0, 0, $context->diagnostics->count());
        }
        if ($statement->chain === true) {
            [$transaction->nextIsolation, $transaction->nextReadOnly] = $characteristics;
            $transaction->begin();
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }
}
