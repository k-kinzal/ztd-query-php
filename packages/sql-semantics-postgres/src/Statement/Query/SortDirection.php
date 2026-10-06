<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

/**
 * The direction written for a sort key.
 *
 * No direction sorts ascending; the absence is kept as null by the sort item.
 * Source: https://www.postgresql.org/docs/17/queries-order.html.
 *
 * @visibility public
 * @example Spelling the descending direction
 *     \SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection::Descending->value // => 'DESC'
 */
enum SortDirection: string
{
    case Ascending = 'ASC';
    case Descending = 'DESC';
}
