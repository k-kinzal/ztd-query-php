<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

/**
 * Whether COPY reads rows into the table (FROM) or writes the rows of the table out (TO).
 *
 * Mirrors the `is_from` flag of PostgreSQL's `CopyStmt`; the value is the keyword.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html.
 *
 * @visibility public
 * @example Reading the direction of COPY
 *     $copy = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('COPY t FROM STDIN');
 *     $copy->statement->direction // => \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyDirection::From
 */
enum CopyDirection: string
{
    case From = 'FROM';
    case To = 'TO';
}
