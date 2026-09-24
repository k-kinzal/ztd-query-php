<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\PartitionChange;

use SqlSemantics\Model\Definition\MySqlTable\Partition\PartitionDefinition;
use SqlSemantics\Model\Definition\MySqlTable\Partition\PartitioningRules;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Adds explicitly defined partitions to a partitioned table.
 * @visibility public
 * @example Adding a range partition
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ADD PARTITION (PARTITION p9 VALUES LESS THAN (90))');
 *     $statement->alterations[0]->partitions[0]->name // => 'p9'
 * @example Rejecting an empty list
 *     new \SqlSemantics\Model\Definition\MySqlTable\PartitionChange\AddPartitions([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AddPartitions implements TableAlteration
{
    /**
     * @param non-empty-list<PartitionDefinition> $partitions Added partitions with unique names
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $partitions, public readonly BinlogPolicy $binlog = BinlogPolicy::Write)
    {
        Collections::objects(Collections::nonEmpty($partitions), PartitionDefinition::class);
        PartitioningRules::names($partitions);
    }
}
