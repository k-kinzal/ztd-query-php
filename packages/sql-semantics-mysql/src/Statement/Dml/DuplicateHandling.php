<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

/**
 * What a statement that copies rows does with a row that duplicates a unique key: REPLACE or IGNORE.
 *
 * Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-select.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling::Ignore->value // => 'IGNORE'
 */
enum DuplicateHandling: string
{
    case Replace = 'REPLACE';
    case Ignore = 'IGNORE';
}
