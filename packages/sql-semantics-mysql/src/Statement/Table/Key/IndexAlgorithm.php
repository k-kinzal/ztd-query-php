<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key;

/**
 * The index structure requested by USING: BTREE, RTREE or HASH.
 *
 * Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html#create-index-storage-engine-index-types.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm::Hash->value // => 'HASH'
 */
enum IndexAlgorithm: string
{
    case Btree = 'BTREE';
    case Rtree = 'RTREE';
    case Hash = 'HASH';
}
