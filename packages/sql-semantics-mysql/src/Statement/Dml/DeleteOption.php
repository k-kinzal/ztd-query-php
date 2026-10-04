<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

/**
 * A modifier of DELETE: LOW_PRIORITY, QUICK or IGNORE.
 *
 * Each case holds the keyword it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/delete.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Dml\DeleteOption::Quick->value // => 'QUICK'
 */
enum DeleteOption: string
{
    case LowPriority = 'LOW_PRIORITY';
    case Quick = 'QUICK';
    case Ignore = 'IGNORE';
}
