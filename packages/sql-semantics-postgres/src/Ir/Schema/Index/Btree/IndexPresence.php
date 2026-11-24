<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

/**
 * A checked name in the indexed relation's namespace.
 *
 * @visibility public
 * @example Referring to a known vacant index name
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexPresence::Vacant->value // => 'vacant'
 */
enum IndexPresence: string
{
    case Vacant = 'vacant';
    case Occupied = 'occupied';
}
