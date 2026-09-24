<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\PartitionChange;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes named partitions together with their rows.
 * @visibility public
 * @example Dropping partitions
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t DROP PARTITION p0, p1');
 *     $statement->alterations[0]->partitions // => ['p0', 'p1']
 */
final class DropPartitions implements TableAlteration
{
    /**
     * @param non-empty-list<string> $partitions Distinct partition names
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $partitions)
    {
        AlterationInvariant::names($partitions);
    }
}
