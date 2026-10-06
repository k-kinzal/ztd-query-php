<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Window;

/**
 * The end of the frame NTH_VALUE counts its rows from.
 *
 * Each case holds the keywords. FROM FIRST is the default; the server does
 * not support FROM LAST.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-function-descriptions.html#function_nth-value.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Call\Window\CountingEdge::Last->value // => 'FROM LAST'
 */
enum CountingEdge: string
{
    case First = 'FROM FIRST';
    case Last = 'FROM LAST';
}
