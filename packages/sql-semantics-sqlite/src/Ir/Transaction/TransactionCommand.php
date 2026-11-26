<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Transaction;

/**
 * The complete set of SQLite transaction-control operations.
 *
 * @visibility public
 * @example Reading the operation that returns to a checkpoint
 *     \SqlSemantics\Platform\Sqlite\Ir\Transaction\TransactionCommand::RollbackTo->value // => 'ROLLBACK TO SAVEPOINT'
 */
enum TransactionCommand: string
{
    case BeginDeferred = 'BEGIN DEFERRED';
    case BeginImmediate = 'BEGIN IMMEDIATE';
    case BeginExclusive = 'BEGIN EXCLUSIVE';
    case Commit = 'COMMIT';
    case Rollback = 'ROLLBACK';
    case Savepoint = 'SAVEPOINT';
    case Release = 'RELEASE SAVEPOINT';
    case RollbackTo = 'ROLLBACK TO SAVEPOINT';
}
