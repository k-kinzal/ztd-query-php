<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Partition;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One named partition: its RANGE or LIST values (none for HASH and KEY), storage options, and subpartitions.
 * @visibility public
 * @example Reading a partition definition
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t PARTITION BY RANGE (id) (PARTITION p0 VALUES LESS THAN (10) TABLESPACE ts)');
 *     $partition = $statement->alterations[0]->partitioning->partitions[0];
 *     [$partition->name, $partition->properties->tablespace] // => ['p0', 'ts']
 */
final class PartitionDefinition
{
    /**
     * @param list<SubpartitionDefinition> $subpartitions Explicit subpartitions in order
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly string $name,
        public readonly RangeBound|ListBound|null $values = null,
        public readonly PartitionProperties $properties = new PartitionProperties(),
        public readonly array $subpartitions = [],
    ) {
        AlterationInvariant::name($name);
        Collections::objects($subpartitions, SubpartitionDefinition::class);
    }
}
