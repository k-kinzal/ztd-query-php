<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Ordering;

/**
 * The placement of NULL values written after an ordering term.
 *
 * Source: https://sqlite.org/lang_select.html#the_order_by_clause.
 *
 * @visibility public
 * @example Reading a written NULL placement
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t ORDER BY a NULLS LAST');
 *     $query->statement->orderBy[0]->nulls // => \SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\NullsOrder::Last
 */
enum NullsOrder: string
{
    case First = 'FIRST';
    case Last = 'LAST';
}
