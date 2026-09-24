<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\PartitionChange;

use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Maintenance\IndexCache\AllPartitions;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;

/**
 * Rebuilds, optimizes, or analyzes selected partitions.
 * @visibility public
 * @example Rebuilding named partitions without binary logging
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t REBUILD PARTITION LOCAL p0, p1');
 *     $statement->alterations[0]->partitions->names // => ['p0', 'p1']
 *     $statement->alterations[0]->binlog // => \SqlSemantics\Model\Maintenance\MySql\BinlogPolicy::Omit
 */
final class ProcessPartitions implements TableAlteration
{
    /**
     * Records the process and its partition selection.
     */
    public function __construct(public readonly PartitionProcess $process, public readonly AllPartitions|NamedPartitions $partitions, public readonly BinlogPolicy $binlog = BinlogPolicy::Write)
    {
    }
}
