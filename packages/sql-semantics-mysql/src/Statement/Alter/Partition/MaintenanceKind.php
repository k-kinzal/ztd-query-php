<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

/**
 * The maintenance operations ALTER TABLE runs on selected partitions.
 *
 * Mirrors the subclasses of PT_alter_table_partition_list_or_all. CHECK
 * takes check options, REPAIR repair options; CHECK and TRUNCATE take no
 * NO_WRITE_TO_BINLOG.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-maintenance.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintenanceKind::Rebuild->value // => 'REBUILD'
 */
enum MaintenanceKind: string
{
    case Rebuild = 'REBUILD';
    case Optimize = 'OPTIMIZE';
    case Analyze = 'ANALYZE';
    case Check = 'CHECK';
    case Repair = 'REPAIR';
    case Truncate = 'TRUNCATE';

    /**
     * Tells whether the operation accepts NO_WRITE_TO_BINLOG.
     */
    public function logged(): bool
    {
        return $this !== self::Check && $this !== self::Truncate;
    }
}
