<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column\Kind;

/**
 * How a generated column keeps its values: VIRTUAL computes them when rows are read, STORED when rows are written.
 *
 * Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-generated-columns.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage::Stored->value // => 'STORED'
 */
enum GeneratedStorage: string
{
    case Virtual = 'VIRTUAL';
    case Stored = 'STORED';
}
