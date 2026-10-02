<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server;

/**
 * The options of REPAIR TABLE and of ALTER TABLE ... REPAIR PARTITION.
 *
 * Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/repair-table.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\RepairOption::UseFrm->value // => 'USE_FRM'
 */
enum RepairOption: string
{
    case Quick = 'QUICK';
    case Extended = 'EXTENDED';
    case UseFrm = 'USE_FRM';
}
