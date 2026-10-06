<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server;

/**
 * The options of CHECK TABLE and of ALTER TABLE ... CHECK PARTITION.
 *
 * Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/check-table.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\CheckOption::ForUpgrade->value // => 'FOR UPGRADE'
 */
enum CheckOption: string
{
    case Quick = 'QUICK';
    case Fast = 'FAST';
    case Medium = 'MEDIUM';
    case Extended = 'EXTENDED';
    case Changed = 'CHANGED';
    case ForUpgrade = 'FOR UPGRADE';
}
