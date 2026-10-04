<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

/**
 * The steps of a two-phase commit.
 *
 * Mirrors `TRANS_STMT_PREPARE`, `TRANS_STMT_COMMIT_PREPARED` and
 * `TRANS_STMT_ROLLBACK_PREPARED` of PostgreSQL's `TransactionStmtKind`. The
 * value is the keyword spelling.
 * Source: https://www.postgresql.org/docs/17/sql-prepare-transaction.html, https://www.postgresql.org/docs/17/sql-commit-prepared.html,
 * https://www.postgresql.org/docs/17/sql-rollback-prepared.html.
 *
 * @visibility public
 * @example Reading the step of a COMMIT PREPARED
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("COMMIT PREPARED 'x'");
 *     $operation->statement->action // => \SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\PreparedAction::Commit
 */
enum PreparedAction: string
{
    case Prepare = 'PREPARE TRANSACTION';
    case Commit = 'COMMIT PREPARED';
    case Rollback = 'ROLLBACK PREPARED';
}
