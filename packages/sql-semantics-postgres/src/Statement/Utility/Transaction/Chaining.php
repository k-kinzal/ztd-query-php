<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

/**
 * Whether a new transaction with the same characteristics starts when one ends.
 *
 * `AND CHAIN` starts one immediately; `AND NO CHAIN` states the default,
 * that none is started. The value is the keyword spelling.
 * Source: https://www.postgresql.org/docs/17/sql-commit.html, https://www.postgresql.org/docs/17/sql-rollback.html.
 *
 * @visibility public
 * @example Reading the chaining of a COMMIT
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('COMMIT AND NO CHAIN');
 *     $operation->statement->chaining // => \SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Chaining::NoChain
 */
enum Chaining: string
{
    case Chain = 'AND CHAIN';
    case NoChain = 'AND NO CHAIN';
}
