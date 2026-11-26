<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

/**
 * The two spellings of the command that aborts the current transaction.
 *
 * `ABORT` is kept for historical reasons and is equivalent to `ROLLBACK`. The value is the keyword.
 * Source: https://www.postgresql.org/docs/17/sql-rollback.html, https://www.postgresql.org/docs/17/sql-abort.html.
 *
 * @visibility SqlSemantics
 */
enum RollbackSpelling: string
{
    case Rollback = 'ROLLBACK';
    case Abort = 'ABORT';
}
