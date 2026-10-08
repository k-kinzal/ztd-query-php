<?php

declare(strict_types=1);

namespace MySqlMemory\Error\Family;

use MySqlMemory\Error\CatalogedError;
use MySqlMemory\Error\ErrorCode;

/**
 * A server error or warning about transactions: their characteristics, read-only transactions, rollbacks that cannot undo everything, transactions that combine storage engines, and row lock waits.
 *
 * The SQLSTATE and message format of each error are those of the server error reference, which resources/errors.php holds.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html,
 * https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html.
 *
 * @visibility public
 * @example Building the error of a lock wait that timed out
 *     \MySqlMemory\Error\Family\TransactionError::LockWaitTimeout->error()->getMessage() // => 'Lock wait timeout exceeded; try restarting transaction'
 */
enum TransactionError: int implements ErrorCode
{
    use CatalogedError;

    case NotCompleteRollback = 1196;
    case LockWaitTimeout = 1205;
    case LockDeadlock = 1213;
    case CharacteristicInTransaction = 1568;
    case ReadOnlyTransaction = 1792;
    case LockNowait = 3572;
    case CombinedEngines = 6414;
}
