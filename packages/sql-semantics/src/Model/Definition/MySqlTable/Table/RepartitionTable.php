<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Table;

use SqlSemantics\Model\Definition\MySqlTable\Partition\TablePartitioning;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;

/**
 * Replaces the partitioning of the altered table (a trailing PARTITION BY clause).
 * @visibility public
 * @example Reading the new partitioning
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t PARTITION BY HASH (id) PARTITIONS 4');
 *     $statement->alterations[0]->partitioning->partitionCount // => 4
 */
final class RepartitionTable implements TableAlteration
{
    /**
     * Records the requested partitioning.
     */
    public function __construct(public readonly TablePartitioning $partitioning)
    {
    }
}
