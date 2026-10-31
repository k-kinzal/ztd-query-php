<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Message;

/**
 * SQLSTATE and message definitions owned by the TransactionError cases.
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * @visibility MySqlMemory\Error
 */
final class TransactionMessages
{
    /**
     * @var array<string, array{string, string}> SQLSTATE and format, indexed by the owning enum case
     */
    public const DEFINITIONS = [
        'NotCompleteRollback' => ['HY000', 'Some non-transactional changed tables couldn\'t be rolled back'],
        'LockWaitTimeout' => ['HY000', 'Lock wait timeout exceeded; try restarting transaction'],
        'LockDeadlock' => ['40001', 'Deadlock found when trying to get lock; try restarting transaction'],
        'CharacteristicInTransaction' => ['25001', 'Transaction characteristics can\'t be changed while a transaction is in progress'],
        'ReadOnlyTransaction' => ['25006', 'Cannot execute statement in a READ ONLY transaction.'],
        'ServerGone' => ['HY000', 'MySQL server has gone away'],
        'LockNowait' => ['HY000', 'Statement aborted because lock(s) could not be acquired immediately and NOWAIT is set.'],
        'CreateTransactionRestricted' => ['HY000', 'Only BINLOG INSERT, COMMIT and ROLLBACK statements are allowed after CREATE TABLE with START TRANSACTION statement.'],
        'CreateTransactionForeignKey' => ['HY000', 'Foreign key creation is not allowed with CREATE TABLE as SELECT and CREATE TABLE with START TRANSACTION statement.'],
        'CreateTransactionInvalid' => ['HY000', 'START TRANSACTION clause cannot be used %s'],
        'CombinedEngines' => ['HY000', 'Combining the storage engines %s and %s is deprecated, but the statement or transaction updates both the %s table %s and the %s table %s.'],
    ];
}
