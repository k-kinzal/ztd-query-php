<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance;

/**
 * The table-level lock modes of LOCK.
 *
 * Mirrors the eight lock modes of PostgreSQL, from the weakest to the
 * strongest. The value is the keyword spelling.
 * Source: https://www.postgresql.org/docs/17/explicit-locking.html#LOCKING-TABLES, https://www.postgresql.org/docs/17/sql-lock.html.
 *
 * @visibility public
 * @example Reading the mode of a LOCK
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('LOCK t IN SHARE ROW EXCLUSIVE MODE');
 *     $operation->statement->mode // => \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\LockMode::ShareRowExclusive
 */
enum LockMode: string
{
    case AccessShare = 'ACCESS SHARE';
    case RowShare = 'ROW SHARE';
    case RowExclusive = 'ROW EXCLUSIVE';
    case ShareUpdateExclusive = 'SHARE UPDATE EXCLUSIVE';
    case Share = 'SHARE';
    case ShareRowExclusive = 'SHARE ROW EXCLUSIVE';
    case Exclusive = 'EXCLUSIVE';
    case AccessExclusive = 'ACCESS EXCLUSIVE';
}
