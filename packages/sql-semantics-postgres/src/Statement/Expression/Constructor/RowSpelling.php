<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor;

/**
 * How a row constructor is written, as PostgreSQL's `CoercionForm` of a `RowExpr` records it.
 *
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-ROW-CONSTRUCTORS.
 *
 * @visibility SqlSemantics
 */
enum RowSpelling
{
    /**
     * `ROW(…)`, with any number of fields.
     */
    case Explicit;

    /**
     * `(a, b, …)`, with at least two fields.
     */
    case Implicit;
}
