<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

/**
 * How a type cast is written.
 *
 * `x::t` and `CAST(x AS t)` request the same conversion; the grammar keeps
 * them as different token sequences, and only the operator form takes part in
 * operator precedence.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-TYPE-CASTS.
 *
 * @visibility SqlSemantics
 */
enum CastSpelling
{
    /**
     * The postfix operator `::`.
     */
    case Operator;

    /**
     * The function-like form `CAST ( x AS t )`.
     */
    case Function;
}
