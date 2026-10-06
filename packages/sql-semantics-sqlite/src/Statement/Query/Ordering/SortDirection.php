<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Ordering;

/**
 * The direction written after an ordering term or an indexed column.
 *
 * Source: https://sqlite.org/lang_select.html#the_order_by_clause.
 *
 * @visibility public
 * @example Reading a written direction
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t ORDER BY a DESC');
 *     $query->statement->orderBy[0]->direction // => \SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection::Descending
 */
enum SortDirection: string
{
    case Ascending = 'ASC';
    case Descending = 'DESC';
}
