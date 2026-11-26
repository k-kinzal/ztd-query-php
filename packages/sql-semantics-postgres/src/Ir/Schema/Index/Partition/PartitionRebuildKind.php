<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition;

/**
 * The physical scope of a rebuild on a partition relation.
 *
 * @visibility public
 * @example Identifying a partitioned owner
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\PartitionRebuildKind::Branch->value // => 'branch'
 */
enum PartitionRebuildKind: string
{
    case Branch = 'branch';
    case Leaf = 'leaf';
}
