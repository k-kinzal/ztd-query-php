<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\PartitionChange;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Swaps the rows of one partition with those of a nonpartitioned table.
 * @visibility public
 * @example Exchanging a partition
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t EXCHANGE PARTITION p0 WITH TABLE u');
 *     [$statement->alterations[0]->partition, $statement->alterations[0]->table->declaration->name] // => ['p0', 'u']
 */
final class ExchangePartition implements TableAlteration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $partition, public readonly TableReference $table)
    {
        AlterationInvariant::name($partition);
        if ($table->alias !== null || count($table->name->parts) > 2) {
            throw new InvalidStructure('An exchanged table is named without an alias by at most a database and a table.');
        }
    }
}
