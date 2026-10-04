<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation\Hint;

/**
 * The part of query processing an index hint applies to: `FOR JOIN`, `FOR ORDER BY` or `FOR GROUP BY`.
 *
 * Without a scope the hint applies to all of them; the absence is kept as
 * written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/index-hints.html.
 *
 * @visibility public
 * @example Reading the scope of an index hint
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t USE INDEX FOR ORDER BY (i)');
 *     $query->statement->from->indexHints[0]->scope // => \SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintScope::OrderBy
 */
enum IndexHintScope
{
    case Join;
    case OrderBy;
    case GroupBy;
}
