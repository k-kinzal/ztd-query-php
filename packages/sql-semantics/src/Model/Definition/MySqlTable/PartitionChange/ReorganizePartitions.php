<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\PartitionChange;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\Partition\PartitionDefinition;
use SqlSemantics\Model\Definition\MySqlTable\Partition\PartitioningRules;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Replaces named partitions by new partition definitions, moving their rows.
 * @visibility public
 * @example Splitting a partition
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t REORGANIZE PARTITION p INTO (PARTITION a VALUES LESS THAN (5), PARTITION b VALUES LESS THAN (10))');
 *     [$statement->alterations[0]->partitions, count($statement->alterations[0]->into)] // => [['p'], 2]
 */
final class ReorganizePartitions implements TableAlteration
{
    /**
     * @param non-empty-list<string> $partitions Replaced partitions
     * @param non-empty-list<PartitionDefinition> $into Replacement partitions
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $partitions, public readonly array $into, public readonly BinlogPolicy $binlog = BinlogPolicy::Write)
    {
        AlterationInvariant::names($partitions);
        Collections::objects(Collections::nonEmpty($into), PartitionDefinition::class);
        PartitioningRules::names($into);
    }
}
