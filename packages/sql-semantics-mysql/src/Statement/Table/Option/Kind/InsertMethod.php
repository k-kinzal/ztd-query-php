<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option\Kind;

/**
 * The table of a MERGE table that receives inserted rows: none, the first or the last.
 *
 * Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/merge-storage-engine.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\InsertMethod::Last->value // => 'LAST'
 */
enum InsertMethod: string
{
    case No = 'NO';
    case First = 'FIRST';
    case Last = 'LAST';
}
