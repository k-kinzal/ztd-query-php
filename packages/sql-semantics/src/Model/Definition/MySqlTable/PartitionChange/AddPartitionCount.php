<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\PartitionChange;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Adds a number of automatically named partitions to a HASH or KEY partitioned table.
 * @visibility public
 * @example Adding two partitions
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ADD PARTITION PARTITIONS 2');
 *     $statement->alterations[0]->count // => 2
 * @example Rejecting zero partitions
 *     new \SqlSemantics\Model\Definition\MySqlTable\PartitionChange\AddPartitionCount(0); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AddPartitionCount implements TableAlteration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly int $count, public readonly BinlogPolicy $binlog = BinlogPolicy::Write)
    {
        AlterationInvariant::positive($count);
    }
}
