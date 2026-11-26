<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Transaction;

/**
 * The native connection-local transaction command roles.
 *
 * @visibility public
 * @example Reading the kind of a rollback to a checkpoint
 *     \SqlSemantics\Platform\MySql\Ir\Transaction\TransactionCommand::RollbackTo->value // => 'rollback-to'
 */
enum TransactionCommand: string
{
    case Begin = 'begin';
    case Commit = 'commit';
    case Rollback = 'rollback';
    case Savepoint = 'savepoint';
    case Release = 'release';
    case RollbackTo = 'rollback-to';
}
