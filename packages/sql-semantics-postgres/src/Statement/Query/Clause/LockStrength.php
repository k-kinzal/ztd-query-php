<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Clause;

/**
 * The row-level lock a locking clause takes.
 *
 * Mirrors PostgreSQL's `LockClauseStrength`.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-FOR-UPDATE-SHARE,
 * https://www.postgresql.org/docs/17/explicit-locking.html#LOCKING-ROWS.
 *
 * @visibility public
 * @example Spelling a lock strength
 *     \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\LockStrength::NoKeyUpdate->value // => 'NO KEY UPDATE'
 */
enum LockStrength: string
{
    case Update = 'UPDATE';
    case NoKeyUpdate = 'NO KEY UPDATE';
    case Share = 'SHARE';
    case KeyShare = 'KEY SHARE';
}
