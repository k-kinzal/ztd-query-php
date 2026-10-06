<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

/**
 * The two spellings of the command that starts a transaction block.
 *
 * `BEGIN` is the PostgreSQL spelling and `START TRANSACTION` the one of the
 * SQL standard; both make the same request. The value is the keyword spelling.
 * Source: https://www.postgresql.org/docs/17/sql-begin.html, https://www.postgresql.org/docs/17/sql-start-transaction.html.
 *
 * @visibility public
 * @example Reading the spelling of a START TRANSACTION
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('START TRANSACTION');
 *     $operation->statement->spelling // => \SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\BeginSpelling::StartTransaction
 */
enum BeginSpelling: string
{
    case Begin = 'BEGIN';
    case StartTransaction = 'START TRANSACTION';
}
