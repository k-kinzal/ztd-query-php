<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * How a table's rows are divided between its partitions.
 */
enum TablePartitionStrategy: string
{
    case Range = 'range';
    case List = 'list';
    case Hash = 'hash';
}
