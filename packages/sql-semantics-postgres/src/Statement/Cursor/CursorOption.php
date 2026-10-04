<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Cursor;

/**
 * An option written between the cursor name and CURSOR: BINARY, ASENSITIVE, INSENSITIVE, SCROLL or NO SCROLL.
 *
 * Mirrors the `CURSOR_OPT_*` bits of PostgreSQL's `DeclareCursorStmt`.
 * Every cursor of PostgreSQL is insensitive, so ASENSITIVE and INSENSITIVE
 * change nothing but cannot be combined; SCROLL and NO SCROLL cannot be
 * combined either. The value is the keyword sequence.
 * Source: https://www.postgresql.org/docs/17/sql-declare.html.
 *
 * @visibility public
 * @example Reading the options of a cursor
 *     $declare = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DECLARE c BINARY NO SCROLL CURSOR FOR SELECT 1');
 *     $declare->statement->options // => [\SqlSemantics\Platform\PostgreSql\Statement\Cursor\CursorOption::Binary, \SqlSemantics\Platform\PostgreSql\Statement\Cursor\CursorOption::NoScroll]
 */
enum CursorOption: string
{
    case Binary = 'BINARY';
    case Asensitive = 'ASENSITIVE';
    case Insensitive = 'INSENSITIVE';
    case Scroll = 'SCROLL';
    case NoScroll = 'NO SCROLL';
}
