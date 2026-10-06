<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\With;

/**
 * The order the SEARCH clause of a recursive query reports its rows in.
 *
 * Source: https://www.postgresql.org/docs/17/queries-with.html#QUERIES-WITH-SEARCH.
 *
 * @visibility public
 * @example Spelling the breadth-first order
 *     \SqlSemantics\Platform\PostgreSql\Statement\Query\With\SearchOrder::Breadth->value // => 'BREADTH'
 */
enum SearchOrder: string
{
    case Depth = 'DEPTH';
    case Breadth = 'BREADTH';
}
