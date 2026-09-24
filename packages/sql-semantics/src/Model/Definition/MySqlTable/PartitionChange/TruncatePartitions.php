<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\PartitionChange;

use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Maintenance\IndexCache\AllPartitions;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;

/**
 * Deletes every row of the selected partitions.
 * @visibility public
 * @example Truncating a partition
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t TRUNCATE PARTITION ALL');
 *     $statement->alterations[0]->partitions // => \SqlSemantics\Model\Maintenance\IndexCache\AllPartitions::All
 */
final class TruncatePartitions implements TableAlteration
{
    /**
     * Records the partition selection.
     */
    public function __construct(public readonly AllPartitions|NamedPartitions $partitions)
    {
    }
}
