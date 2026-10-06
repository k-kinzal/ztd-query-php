<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping;

/**
 * The kind of a grouping set written in GROUP BY.
 *
 * Mirrors PostgreSQL's `GroupingSetKind` without SIMPLE, which the server
 * only creates internally: a plain grouping expression is kept as the
 * expression itself.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-GROUPING-SETS.
 *
 * @visibility public
 * @example Spelling a grouping set kind
 *     \SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingSetKind::Sets->value // => 'GROUPING SETS'
 */
enum GroupingSetKind: string
{
    case Empty = '';
    case Rollup = 'ROLLUP';
    case Cube = 'CUBE';
    case Sets = 'GROUPING SETS';
}
