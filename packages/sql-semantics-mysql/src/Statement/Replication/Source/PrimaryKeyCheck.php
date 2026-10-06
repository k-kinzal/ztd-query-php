<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Source;

/**
 * The settings of REQUIRE_TABLE_PRIMARY_KEY_CHECK: the server's LEX_MI_PK_CHECK_* values.
 *
 * Each case holds the keyword it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html#crs-opt-require_table_primary_key_check.
 *
 * @visibility public
 * @example Reading the keyword of a setting
 *     \SqlSemantics\Platform\MySql\Statement\Replication\Source\PrimaryKeyCheck::Generate->value // => 'GENERATE'
 */
enum PrimaryKeyCheck: string
{
    case Stream = 'STREAM';
    case On = 'ON';
    case Off = 'OFF';
    case Generate = 'GENERATE';
}
