<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Partition;

/**
 * How a partitioned table routes each row to one of its partitions.
 *
 * @visibility public
 * @example Classifying the partitioning strategy
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER) PARTITION BY HASH (id)')->tables[0];
 *     $table->properties->partitioning->strategy // => \SqlSemantics\Schema\Partition\PartitionStrategy::Hash
 *     \SqlSemantics\Schema\Partition\PartitionStrategy::from('RANGE') // => \SqlSemantics\Schema\Partition\PartitionStrategy::Range
 */
enum PartitionStrategy: string
{
    case Range = 'RANGE';
    case List = 'LIST';
    case Hash = 'HASH';
}
