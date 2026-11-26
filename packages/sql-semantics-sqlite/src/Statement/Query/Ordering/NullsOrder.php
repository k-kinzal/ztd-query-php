<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Ordering;

/**
 * The placement of NULL values written after an ordering term.
 *
 * Source: https://sqlite.org/lang_select.html#the_order_by_clause.
 *
 * @visibility SqlSemantics
 */
enum NullsOrder: string
{
    case First = 'FIRST';
    case Last = 'LAST';
}
