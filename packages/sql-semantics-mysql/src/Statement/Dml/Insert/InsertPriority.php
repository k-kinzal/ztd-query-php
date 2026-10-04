<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

/**
 * The scheduling modifier of INSERT and REPLACE: LOW_PRIORITY, DELAYED or HIGH_PRIORITY.
 *
 * Each case holds the keyword it is written with. REPLACE takes
 * LOW_PRIORITY and DELAYED only. Since MySQL 5.7 the server accepts DELAYED
 * and ignores it; the request is kept as written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/insert.html, https://dev.mysql.com/doc/refman/8.4/en/replace.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertPriority::Low->value // => 'LOW_PRIORITY'
 */
enum InsertPriority: string
{
    case Low = 'LOW_PRIORITY';
    case Delayed = 'DELAYED';
    case High = 'HIGH_PRIORITY';
}
