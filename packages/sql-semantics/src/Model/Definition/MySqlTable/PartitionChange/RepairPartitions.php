<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\PartitionChange;

use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Maintenance\IndexCache\AllPartitions;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Maintenance\MySql\RepairOption;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Repairs selected partitions with explicitly requested repair modes.
 * @visibility public
 * @example Repairing a partition
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t REPAIR PARTITION p0 EXTENDED');
 *     $statement->alterations[0]->options // => [\SqlSemantics\Model\Maintenance\MySql\RepairOption::Extended]
 */
final class RepairPartitions implements TableAlteration
{
    /**
     * @param list<RepairOption> $options Requested repair modes in SQL order
     * @throws InvalidStructure
     */
    public function __construct(public readonly AllPartitions|NamedPartitions $partitions, public readonly BinlogPolicy $binlog = BinlogPolicy::Write, public readonly array $options = [])
    {
        Collections::objects($options, RepairOption::class);
    }
}
