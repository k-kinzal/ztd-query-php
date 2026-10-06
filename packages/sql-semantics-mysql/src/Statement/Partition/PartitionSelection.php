<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition;

use SqlSemantics\Statement\Node;

/**
 * The partitions a maintenance operation names: ALL, or a list of partition names.
 *
 * The table change family provides the structure; ANALYZE, CHECK, OPTIMIZE and
 * REPAIR TABLE hold it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table-partition-operations.html.
 */
interface PartitionSelection extends Node
{
}
