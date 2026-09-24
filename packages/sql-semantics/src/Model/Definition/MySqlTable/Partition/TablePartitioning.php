<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Partition;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A complete MySQL PARTITION BY clause: the function, partition and subpartition counts, and explicit partitions.
 * RANGE and LIST require explicit partitions with matching values, HASH and KEY take no values, and only RANGE and LIST subpartition.
 * @visibility public
 * @example Reading a partitioning clause
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t PARTITION BY RANGE (id) SUBPARTITION BY KEY (id) SUBPARTITIONS 2 (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN MAXVALUE)');
 *     $partitioning = $statement->alterations[0]->partitioning;
 *     [$partitioning->partitionCount, $partitioning->subpartitionCount, count($partitioning->partitions)] // => [null, 2, 2]
 *     $partitioning->subpartitioning instanceof \SqlSemantics\Model\Definition\MySqlTable\Partition\KeyPartitioning // => true
 * @example Rejecting a RANGE partitioning without partitions
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $hash = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t PARTITION BY HASH (id)')->alterations[0]->partitioning->function;
 *     new \SqlSemantics\Model\Definition\MySqlTable\Partition\TablePartitioning(new \SqlSemantics\Model\Definition\MySqlTable\Partition\ExpressionPartitioning(\SqlSemantics\Schema\Partition\PartitionStrategy::Range, $hash->expression)); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class TablePartitioning
{
    /**
     * @param list<PartitionDefinition> $partitions Explicit partitions in order
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly PartitionFunction $function,
        public readonly ?int $partitionCount = null,
        public readonly HashPartitioning|KeyPartitioning|null $subpartitioning = null,
        public readonly ?int $subpartitionCount = null,
        public readonly array $partitions = [],
    ) {
        Collections::objects($partitions, PartitionDefinition::class);
        PartitioningRules::counts($partitionCount, $subpartitioning, $subpartitionCount, $partitions);
        PartitioningRules::values($function, $partitions);
        PartitioningRules::subpartitions($function, $subpartitioning, $subpartitionCount, $partitions);
        PartitioningRules::names($partitions);
    }
}
