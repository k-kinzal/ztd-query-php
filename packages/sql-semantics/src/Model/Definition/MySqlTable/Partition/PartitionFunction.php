<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Partition;

/**
 * How MySQL assigns each row to a partition: HASH, KEY, RANGE, LIST, or their COLUMNS forms.
 * @visibility public
 * @example Reading the partitioning function
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t PARTITION BY KEY (id) PARTITIONS 2');
 *     $statement->alterations[0]->partitioning->function instanceof \SqlSemantics\Model\Definition\MySqlTable\Partition\PartitionFunction // => true
 */
interface PartitionFunction
{
}
