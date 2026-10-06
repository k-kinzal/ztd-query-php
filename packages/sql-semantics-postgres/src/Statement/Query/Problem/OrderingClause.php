<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Problem;

/**
 * The clauses in which an integer constant names an output column by its position.
 *
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-ORDERBY,
 * https://www.postgresql.org/docs/17/sql-select.html#SQL-GROUPBY, https://www.postgresql.org/docs/17/sql-select.html#SQL-DISTINCT.
 *
 * @visibility public
 * @example Spelling the clause
 *     \SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\OrderingClause::GroupBy->value // => 'GROUP BY'
 */
enum OrderingClause: string
{
    case OrderBy = 'ORDER BY';
    case GroupBy = 'GROUP BY';
    case DistinctOn = 'DISTINCT ON';
}
