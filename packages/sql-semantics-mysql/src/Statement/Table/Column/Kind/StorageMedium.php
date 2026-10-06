<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column\Kind;

/**
 * Where an NDB table or column is stored: STORAGE DISK, MEMORY or DEFAULT.
 *
 * Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-ndb-column-options.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\StorageMedium::Disk->value // => 'DISK'
 */
enum StorageMedium: string
{
    case Default = 'DEFAULT';
    case Disk = 'DISK';
    case Memory = 'MEMORY';
}
