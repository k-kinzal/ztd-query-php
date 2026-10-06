<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Handler;

/**
 * Which row HANDLER ... READ index FIRST|NEXT|PREV|LAST fetches in index order.
 *
 * Each case holds the keyword it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/handler.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Dml\Handler\IndexDirection::Previous->value // => 'PREV'
 */
enum IndexDirection: string
{
    case First = 'FIRST';
    case Next = 'NEXT';
    case Previous = 'PREV';
    case Last = 'LAST';
}
