<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Partition;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Partition\PartitionStrategy;

/**
 * Assigns rows by comparing column tuples with RANGE COLUMNS or LIST COLUMNS partition values.
 * @visibility public
 * @example Reading a list columns function
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a INT, b INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t PARTITION BY LIST COLUMNS (a, b) (PARTITION p VALUES IN ((1, 2)))');
 *     $statement->alterations[0]->partitioning->function->columns // => ['a', 'b']
 * @example Rejecting an empty column list
 *     new \SqlSemantics\Model\Definition\MySqlTable\Partition\ColumnsPartitioning(\SqlSemantics\Schema\Partition\PartitionStrategy::Range, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class ColumnsPartitioning implements PartitionFunction
{
    /**
     * @param non-empty-list<string> $columns Compared columns in tuple order
     * @throws InvalidStructure
     */
    public function __construct(public readonly PartitionStrategy $strategy, public readonly array $columns)
    {
        if ($strategy === PartitionStrategy::Hash) {
            throw new InvalidStructure('COLUMNS partitioning is RANGE or LIST.');
        }
        AlterationInvariant::names($columns);
    }
}
