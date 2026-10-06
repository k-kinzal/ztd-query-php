<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Handler;

/**
 * Which row HANDLER ... READ FIRST|NEXT fetches in the natural row order of the table.
 *
 * Each case holds the keyword it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/handler.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection::Next->value // => 'NEXT'
 */
enum ScanDirection: string
{
    case First = 'FIRST';
    case Next = 'NEXT';
}
