<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Partition;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One named subpartition of a partition, with its storage options.
 * @visibility public
 * @example Reading a subpartition
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t PARTITION BY RANGE (id) SUBPARTITION BY HASH (id) (PARTITION p VALUES LESS THAN MAXVALUE (SUBPARTITION s0, SUBPARTITION s1))');
 *     $statement->alterations[0]->partitioning->partitions[0]->subpartitions[1]->name // => 's1'
 */
final class SubpartitionDefinition
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly PartitionProperties $properties = new PartitionProperties())
    {
        AlterationInvariant::name($name);
    }
}
