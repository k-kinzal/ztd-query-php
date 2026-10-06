<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key;

/**
 * The MATCH clause of a foreign key reference, which InnoDB parses and ignores.
 *
 * Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceMatch::Full->value // => 'FULL'
 */
enum ReferenceMatch: string
{
    case Full = 'FULL';
    case Partial = 'PARTIAL';
    case Simple = 'SIMPLE';
}
