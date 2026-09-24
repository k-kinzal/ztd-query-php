<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\PartitionChange;

use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;

/**
 * Reorganizes every partition of an automatically partitioned table (REORGANIZE PARTITION without a partition list).
 * @visibility public
 * @example Reorganizing without a list
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t REORGANIZE PARTITION');
 *     $statement->alterations[0]->binlog // => \SqlSemantics\Model\Maintenance\MySql\BinlogPolicy::Write
 */
final class RebuildPartitioning implements TableAlteration
{
    /**
     * Records the binary logging policy.
     */
    public function __construct(public readonly BinlogPolicy $binlog = BinlogPolicy::Write)
    {
    }
}
