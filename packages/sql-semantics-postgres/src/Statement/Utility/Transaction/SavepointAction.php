<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

/**
 * What a command does with a savepoint.
 *
 * Mirrors `TRANS_STMT_SAVEPOINT`, `TRANS_STMT_RELEASE` and
 * `TRANS_STMT_ROLLBACK_TO` of PostgreSQL's `TransactionStmtKind`.
 * Source: https://www.postgresql.org/docs/17/sql-savepoint.html, https://www.postgresql.org/docs/17/sql-release-savepoint.html,
 * https://www.postgresql.org/docs/17/sql-rollback-to.html.
 *
 * @visibility public
 * @example Reading the action of a ROLLBACK TO
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ROLLBACK TO s');
 *     $operation->statement->action // => \SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SavepointAction::RollbackTo
 */
enum SavepointAction
{
    case Define;
    case Release;
    case RollbackTo;
}
