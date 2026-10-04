<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Method;

/**
 * The two partitioning types that assign rows by the partition values each partition definition states.
 *
 * RANGE partitions hold the rows below a bound (`VALUES LESS THAN`); LIST
 * partitions hold the rows whose value is one of a list (`VALUES IN`).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-range.html,
 * https://dev.mysql.com/doc/refman/8.4/en/partitioning-list.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind::List->value // => 'LIST'
 */
enum PartitionKind: string
{
    case Range = 'RANGE';
    case List = 'LIST';
}
