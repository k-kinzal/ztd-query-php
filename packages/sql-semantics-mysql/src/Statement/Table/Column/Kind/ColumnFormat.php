<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column\Kind;

/**
 * The storage format of a column of an NDB table: COLUMN_FORMAT FIXED, DYNAMIC or DEFAULT.
 *
 * Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-ndb-column-options.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnFormat::Fixed->value // => 'FIXED'
 */
enum ColumnFormat: string
{
    case Default = 'DEFAULT';
    case Fixed = 'FIXED';
    case Dynamic = 'DYNAMIC';
}
