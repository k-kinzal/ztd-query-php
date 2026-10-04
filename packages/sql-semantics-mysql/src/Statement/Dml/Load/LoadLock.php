<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Load;

/**
 * The scheduling modifier of LOAD: LOW_PRIORITY or CONCURRENT.
 *
 * Each case holds the keyword it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadLock::Concurrent->value // => 'CONCURRENT'
 */
enum LoadLock: string
{
    case LowPriority = 'LOW_PRIORITY';
    case Concurrent = 'CONCURRENT';
}
