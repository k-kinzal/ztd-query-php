<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Recursion;

/**
 * The order in which a PostgreSQL SEARCH clause numbers the rows of a recursive query.
 * @visibility public
 * @example Reading the search order
 *     \SqlSemantics\Model\Query\Recursion\SearchOrder::DepthFirst->value // => 'DEPTH FIRST'
 */
enum SearchOrder: string
{
    case BreadthFirst = 'BREADTH FIRST';
    case DepthFirst = 'DEPTH FIRST';
}
