<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Maintenance;

/**
 * The method CHECKSUM TABLE computes the checksum by: QUICK or EXTENDED.
 *
 * Mirrors T_QUICK and T_EXTEND of check_opt.flags. Without a method the
 * server reads the live checksum when the table keeps one and computes it
 * otherwise.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/checksum-table.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumMode::Extended->value // => 'EXTENDED'
 */
enum ChecksumMode: string
{
    case Quick = 'QUICK';
    case Extended = 'EXTENDED';
}
