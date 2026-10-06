<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

/**
 * The sort direction written after an ordering expression or a key part.
 *
 * Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sorting-rows.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Query\Direction::Descending->value // => 'DESC'
 */
enum Direction: string
{
    case Ascending = 'ASC';
    case Descending = 'DESC';
}
