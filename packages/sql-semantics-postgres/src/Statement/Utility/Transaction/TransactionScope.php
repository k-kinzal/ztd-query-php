<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

/**
 * Which transactions SET TRANSACTION characteristics apply to.
 *
 * `SET TRANSACTION` sets the current transaction; `SET SESSION
 * CHARACTERISTICS AS TRANSACTION` sets the default of the later transactions
 * of the session. The value is the keyword spelling.
 * Source: https://www.postgresql.org/docs/17/sql-set-transaction.html.
 *
 * @visibility public
 * @example Reading the scope of session characteristics
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');
 *     $operation->statement->scope // => \SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\TransactionScope::SessionCharacteristics
 */
enum TransactionScope: string
{
    case Transaction = 'TRANSACTION';
    case SessionCharacteristics = 'SESSION CHARACTERISTICS AS TRANSACTION';
}
