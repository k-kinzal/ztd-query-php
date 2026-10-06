<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

/**
 * The quantifier written after a set operator or after GROUP BY.
 *
 * Without a quantifier a set operation removes duplicate rows and GROUP BY
 * keeps duplicate grouping sets; the absence is kept as null by the holder.
 * Source: https://www.postgresql.org/docs/17/queries-union.html,
 * https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-GROUPING-SETS.
 *
 * @visibility public
 * @example Reading the quantifier of UNION ALL
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 UNION ALL SELECT 2');
 *     $query->statement->quantifier // => \SqlSemantics\Platform\PostgreSql\Statement\Query\SetQuantifier::All
 */
enum SetQuantifier: string
{
    case All = 'ALL';
    case Distinct = 'DISTINCT';
}
