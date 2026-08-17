<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

enum TransactionOperation: string
{
    case Begin = 'begin';
    case Commit = 'commit';
    case Rollback = 'rollback';
    case Savepoint = 'savepoint';
    case RollbackTo = 'rollback_to';
    case Release = 'release';
}
