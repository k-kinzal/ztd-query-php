<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option\Kind;

/**
 * The physical row format a table requests with ROW_FORMAT.
 *
 * Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat::Dynamic->value // => 'DYNAMIC'
 */
enum RowFormat: string
{
    case Default = 'DEFAULT';
    case Fixed = 'FIXED';
    case Dynamic = 'DYNAMIC';
    case Compressed = 'COMPRESSED';
    case Redundant = 'REDUNDANT';
    case Compact = 'COMPACT';
}
