<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\PartitionChange;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Merges away a number of partitions of a HASH or KEY partitioned table.
 * @visibility public
 * @example Coalescing partitions
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t COALESCE PARTITION 2');
 *     $statement->alterations[0]->count // => 2
 */
final class CoalescePartitions implements TableAlteration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly int $count, public readonly BinlogPolicy $binlog = BinlogPolicy::Write)
    {
        AlterationInvariant::positive($count);
    }
}
