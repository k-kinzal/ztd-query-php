<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Lock;

/**
 * The lock LOCK TABLES takes on one table.
 *
 * Mirrors the thr_lock_type of table_lock: READ (TL_READ_NO_INSERT), READ
 * LOCAL (TL_READ), WRITE (TL_WRITE_DEFAULT) and, before MySQL 8.0,
 * LOW_PRIORITY WRITE (TL_WRITE_LOW_PRIORITY; deprecated in 5.6 and without
 * effect from 5.6.5). Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-tables.html,
 * https://dev.mysql.com/doc/refman/5.7/en/lock-tables.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode::ReadLocal->value // => 'READ LOCAL'
 */
enum LockMode: string
{
    case Read = 'READ';
    case ReadLocal = 'READ LOCAL';
    case Write = 'WRITE';
    case LowPriorityWrite = 'LOW_PRIORITY WRITE';
}
