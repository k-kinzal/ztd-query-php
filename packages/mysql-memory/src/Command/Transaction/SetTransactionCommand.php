<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Transaction;

use MySqlMemory\Command\Command;
use MySqlMemory\Concurrency\Isolation;
use MySqlMemory\Error\Family\TransactionError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\AccessMode;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\IsolationLevel;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetTransaction;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Operation;

/**
 * Executes SET TRANSACTION: the isolation level and access mode of the next transaction, of the session or of the server.
 *
 * Without GLOBAL or SESSION the characteristics apply to the next transaction only, and setting
 * them while a transaction is active is ER_CANT_CHANGE_TX_CHARACTERISTICS; SET
 * @@transaction_isolation and SET @@transaction_read_only, written without a scope, do the same.
 * SESSION sets transaction_isolation and transaction_read_only of the session, which the
 * transactions that start later take, and GLOBAL those of the server, which sessions that connect
 * later take; both are allowed inside a transaction, which keeps its own characteristics. Before
 * MySQL 8.0 the variables are tx_isolation and tx_read_only, which 5.7 keeps beside the new names
 * (verified on live 5.6.51, 5.7.44 and 8.4.7 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-transaction.html.
 *
 * @visibility MySqlMemory
 */
final class SetTransactionCommand implements Command
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
     * Sets the characteristics.
     *
     * @throws SqlError When the characteristics of the next transaction are set while a transaction is active
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof SetTransaction);
        foreach ($statement->characteristics as $characteristic) {
            $isolation = $characteristic instanceof IsolationLevel;
            $value = $isolation ? Isolation::of($characteristic)->value : ($characteristic === AccessMode::ReadOnly ? 'ON' : 'OFF');
            if ($statement->scope === null) {
                $this->next($session, $isolation ? 'transaction_isolation' : 'transaction_read_only', $value);
            } else {
                $this->store($session, $statement->scope, $isolation ? 'transaction_isolation' : 'transaction_read_only', $value);
            }
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }

    /**
     * Gives the next transaction of the session a characteristic, as transaction_isolation or transaction_read_only names it.
     *
     * @param string $value The isolation level as the variable holds it, or ON or OFF
     *
     * @throws SqlError When a transaction is active
     */
    public function next(Session $session, string $name, string $value): void
    {
        $transaction = $session->transaction;
        if ($transaction->active()) {
            throw TransactionError::CharacteristicInTransaction->error();
        }
        if (str_ends_with($name, 'isolation')) {
            $transaction->nextIsolation = Isolation::named($value) ?? Isolation::RepeatableRead;
        } else {
            $transaction->nextReadOnly = $value === 'ON';
        }
    }

    /**
     * Sets a characteristic of the session or the server, under the names the release gives it.
     *
     * @param string $value The isolation level as the variable holds it, or ON or OFF
     */
    public function store(Session $session, VariableScope $scope, string $name, string $value): void
    {
        $suffix = str_ends_with($name, 'isolation') ? 'isolation' : 'read_only';
        foreach (['transaction_' . $suffix, 'tx_' . $suffix] as $alias) {
            $definition = $session->variables->catalog->find($alias);
            if ($definition === null) {
                continue;
            }
            if ($scope === VariableScope::Session) {
                $session->variables->set($definition, $value);
            } else {
                $session->variables->globals->set($definition, $value);
            }
        }
    }
}
