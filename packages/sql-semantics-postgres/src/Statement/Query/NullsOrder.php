<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

/**
 * Where NULL values sort relative to other values.
 *
 * Without the clause NULLs sort as larger than any other value.
 * Source: https://www.postgresql.org/docs/17/queries-order.html.
 *
 * @visibility public
 * @example Spelling the choice that puts NULLs first
 *     \SqlSemantics\Platform\PostgreSql\Statement\Query\NullsOrder::First->value // => 'FIRST'
 */
enum NullsOrder: string
{
    case First = 'FIRST';
    case Last = 'LAST';
}
