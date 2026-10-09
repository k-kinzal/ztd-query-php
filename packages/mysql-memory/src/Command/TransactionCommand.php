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
 * ended, and RELEASE ends the session (see end()). WITH CONSISTENT SNAPSHOT at another level than REPEATABLE READ is ignored with InnoDB's
 * warning 138 (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers).
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
        if ($statement instanceof Commit || $statement instanceof Rollback) {
            $this->end($statement, $session, $context);

            return new Completion(0, 0, $context->diagnostics->count());
        }
        $transaction = $session->transaction;
        $transaction->guard();
        $transaction->end();
        $characteristics = $statement instanceof StartTransaction ? $statement->characteristics : [];
        $snapshot = in_array(TransactionCharacteristic::WithConsistentSnapshot, $characteristics, true);
        $transaction->begin(
            in_array(TransactionCharacteristic::ReadOnly, $characteristics, true) ? true : (in_array(TransactionCharacteristic::ReadWrite, $characteristics, true) ? false : null),
            $snapshot,
        );
        if ($snapshot && $transaction->isolation !== \MySqlMemory\Concurrency\Isolation::RepeatableRead) {
            $context->diagnostics->warning(138, 'InnoDB: WITH CONSISTENT SNAPSHOT was ignored because this phrase can only be used with REPEATABLE READ isolation level.');
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }

    /**
     * Commits or rolls back the transaction, then chains a new one or releases the session as the statement or completion_type asks.
     *
     * RELEASE, written or the default completion_type=RELEASE gives, ends the session, whatever
     * chain is written; NO RELEASE keeps it. Without RELEASE, AND CHAIN, written or the default
     * completion_type=CHAIN gives, opens a transaction (verified on live 5.6.51, 5.7.44, 8.0.44,
     * 8.4.7 and 9.1.0 servers).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_completion_type.
     *
     * @throws \MySqlMemory\Error\SqlError When an XA transaction is active or idle
     */
    public function end(Commit|Rollback $statement, Session $session, Context $context): void
    {
        $transaction = $session->transaction;
        $characteristics = [$transaction->isolation, $transaction->readOnly];
        if ($statement instanceof Commit) {
            $transaction->commit();
        } else {
            $this->rollback($session, $context);
        }
        $completion = strtoupper((string) $session->variables->read('completion_type'));
        if ($statement->release ?? in_array($completion, ['RELEASE', '2'], true)) {
            $session->release();

            return;
        }
        if ($statement->chain ?? in_array($completion, ['CHAIN', '1'], true)) {
            [$transaction->nextIsolation, $transaction->nextReadOnly] = $characteristics;
            $transaction->begin();
        }
    }

    /**
     * Rolls back the transaction, warning with ER_WARNING_NOT_COMPLETE_ROLLBACK when it changed a table that is not transactional, then about the temporary tables it created or dropped.
     *
     * @throws \MySqlMemory\Error\SqlError When an XA transaction is active or idle
     */
    public function rollback(Session $session, Context $context): void
    {
        $transaction = $session->transaction;
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
    }
}
