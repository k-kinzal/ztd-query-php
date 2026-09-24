<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Partition;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Partition\PartitionStrategy;

/**
 * Assigns rows by comparing one integer expression with RANGE or LIST partition values.
 * @visibility public
 * @example Reading a range function
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t PARTITION BY RANGE (id) (PARTITION p0 VALUES LESS THAN (10))');
 *     $statement->alterations[0]->partitioning->function->strategy // => \SqlSemantics\Schema\Partition\PartitionStrategy::Range
 */
final class ExpressionPartitioning implements PartitionFunction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly PartitionStrategy $strategy, public readonly Expression $expression)
    {
        if ($strategy === PartitionStrategy::Hash) {
            throw new InvalidStructure('HASH partitioning has its own function form.');
        }
        AlterationInvariant::expression($expression);
    }
}
