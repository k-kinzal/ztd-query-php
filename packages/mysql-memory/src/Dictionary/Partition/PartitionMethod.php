<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary\Partition;

/**
 * The method a partitioned table splits its rows by.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-types.html.
 *
 * @visibility MySqlMemory
 */
enum PartitionMethod
{
    case Range;
    case RangeColumns;
    case List;
    case ListColumns;
    case Hash;
    case Key;
}
