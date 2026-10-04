<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

/**
 * The two spellings of the command that commits the current transaction.
 *
 * `END` is a PostgreSQL extension equivalent to `COMMIT`. The value is the keyword.
 * Source: https://www.postgresql.org/docs/17/sql-commit.html, https://www.postgresql.org/docs/17/sql-end.html.
 *
 * @visibility public
 * @example Reading the spelling of an END
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('END');
 *     $operation->statement->spelling // => \SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\CommitSpelling::End
 */
enum CommitSpelling: string
{
    case Commit = 'COMMIT';
    case End = 'END';
}
