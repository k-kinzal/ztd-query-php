<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Ordering;

/**
 * The direction written after an ordering term or an indexed column.
 *
 * Source: https://sqlite.org/lang_select.html#the_order_by_clause.
 *
 * @visibility SqlSemantics
 */
enum SortDirection: string
{
    case Ascending = 'ASC';
    case Descending = 'DESC';
}
