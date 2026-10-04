<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Locking;

/**
 * What a locking read does with a row another transaction has locked: `NOWAIT` or `SKIP LOCKED`.
 *
 * Without an action the read waits for the lock.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html.
 *
 * @visibility public
 * @example Reading the action of a locking read
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t FOR UPDATE SKIP LOCKED');
 *     $query->statement->locking[0]->action // => \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockedRowAction::SkipLocked
 */
enum LockedRowAction
{
    case Nowait;
    case SkipLocked;
}
