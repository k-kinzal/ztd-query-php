<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

/**
 * What a transaction statement does to the shadow.
 */
enum TransactionOperation: string
{
    case Begin = 'begin';
    case Commit = 'commit';
    case Rollback = 'rollback';
    case Savepoint = 'savepoint';
    case RollbackTo = 'rollback_to';
    case Release = 'release';
}
