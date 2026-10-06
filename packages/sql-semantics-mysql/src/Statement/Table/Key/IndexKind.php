<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key;

/**
 * The kind of an index: a plain index, a primary key, a unique index, a full-text index or a spatial index.
 *
 * Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-indexes-keys.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Primary->value // => 'PRIMARY KEY'
 */
enum IndexKind: string
{
    case Index = 'INDEX';
    case Primary = 'PRIMARY KEY';
    case Unique = 'UNIQUE';
    case FullText = 'FULLTEXT';
    case Spatial = 'SPATIAL';
}
