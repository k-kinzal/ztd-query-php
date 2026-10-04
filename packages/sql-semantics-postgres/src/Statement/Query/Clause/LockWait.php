<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Clause;

/**
 * What a locking clause does when a row is locked by another transaction; without it the query waits.
 *
 * Mirrors PostgreSQL's `LockWaitPolicy` (the absence is kept as null).
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-FOR-UPDATE-SHARE.
 *
 * @visibility public
 * @example Spelling the policy that skips locked rows
 *     \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\LockWait::SkipLocked->value // => 'SKIP LOCKED'
 */
enum LockWait: string
{
    case NoWait = 'NOWAIT';
    case SkipLocked = 'SKIP LOCKED';
}
