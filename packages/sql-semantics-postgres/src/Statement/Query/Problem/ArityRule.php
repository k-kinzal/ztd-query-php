<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Problem;

/**
 * The places where PostgreSQL requires two column counts to agree.
 *
 * Source: https://www.postgresql.org/docs/17/queries-union.html, https://www.postgresql.org/docs/17/sql-values.html,
 * https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-TABLE-ALIASES.
 *
 * @visibility public
 * @example Naming the rule of set operations
 *     \SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityRule::SetOperation->value // => 'each %1$s query must have the same number of columns'
 */
enum ArityRule: string
{
    case SetOperation = 'each %1$s query must have the same number of columns';
    case ValuesRows = 'VALUES lists must all be the same length';
    case ColumnAliases = 'table "%1$s" has %2$d columns available but %3$d columns specified';
    case CommonTableColumns = 'WITH query "%1$s" has %2$d columns available but %3$d columns specified';
}
