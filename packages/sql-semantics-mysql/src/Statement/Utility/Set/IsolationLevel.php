<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

/**
 * A transaction isolation level of SET TRANSACTION.
 *
 * Each case holds the keywords after ISOLATION LEVEL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-transaction-isolation-levels.html.
 *
 * @visibility public
 * @example Reading the keywords of a level
 *     \SqlSemantics\Platform\MySql\Statement\Utility\Set\IsolationLevel::RepeatableRead->value // => 'REPEATABLE READ'
 */
enum IsolationLevel: string
{
    case ReadUncommitted = 'READ UNCOMMITTED';
    case ReadCommitted = 'READ COMMITTED';
    case RepeatableRead = 'REPEATABLE READ';
    case Serializable = 'SERIALIZABLE';
}
