<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor;

/**
 * Whether a cursor outlives the transaction that created it: WITH HOLD or WITHOUT HOLD.
 *
 * Mirrors the `CURSOR_OPT_HOLD` bit of PostgreSQL's `DeclareCursorStmt`;
 * WITHOUT HOLD is also what a cursor without either clause is. The value is
 * the first keyword.
 * Source: https://www.postgresql.org/docs/17/sql-declare.html.
 *
 * @visibility public
 * @example Reading the holdability of a cursor
 *     $declare = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DECLARE c CURSOR WITH HOLD FOR SELECT 1');
 *     $declare->statement->hold // => \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\Holdability::With
 */
enum Holdability: string
{
    case With = 'WITH';
    case Without = 'WITHOUT';
}
