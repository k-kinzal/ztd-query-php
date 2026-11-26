<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Locking;

/**
 * The lock a locking read takes: `FOR UPDATE`, `FOR SHARE`, or the older `LOCK IN SHARE MODE`.
 *
 * `LOCK IN SHARE MODE` takes a shared lock like `FOR SHARE` but accepts no
 * table list and no NOWAIT or SKIP LOCKED.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html.
 *
 * @visibility SqlSemantics
 */
enum LockStrength
{
    case Update;
    case Share;
    case ShareMode;
}
