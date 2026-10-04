<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition;

/**
 * The options of a partition or subpartition definition, by the keywords they are written with.
 *
 * TABLESPACE and ENGINE take a name, NODEGROUP, MAX_ROWS and MIN_ROWS a
 * number, DATA DIRECTORY, INDEX DIRECTORY and COMMENT a string. The optional
 * STORAGE before ENGINE does not change the option.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-partitioning.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Partition\PartitionOptionKind::DataDirectory->value // => 'DATA DIRECTORY'
 */
enum PartitionOptionKind: string
{
    case Tablespace = 'TABLESPACE';
    case Engine = 'ENGINE';
    case NodeGroup = 'NODEGROUP';
    case MaxRows = 'MAX_ROWS';
    case MinRows = 'MIN_ROWS';
    case DataDirectory = 'DATA DIRECTORY';
    case IndexDirectory = 'INDEX DIRECTORY';
    case Comment = 'COMMENT';

    /**
     * Tells whether the option takes a name.
     */
    public function named(): bool
    {
        return $this === self::Tablespace || $this === self::Engine;
    }

    /**
     * Tells whether the option takes a number.
     */
    public function numbered(): bool
    {
        return $this === self::NodeGroup || $this === self::MaxRows || $this === self::MinRows;
    }
}
