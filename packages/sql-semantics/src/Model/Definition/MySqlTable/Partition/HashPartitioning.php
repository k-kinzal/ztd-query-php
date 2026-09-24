<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Partition;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Assigns rows by the value of an integer expression, optionally with linear powers-of-two hashing.
 * @visibility public
 * @example Reading a linear hash function
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t PARTITION BY LINEAR HASH (id + 1)');
 *     $statement->alterations[0]->partitioning->function->linear // => true
 */
final class HashPartitioning implements PartitionFunction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $expression, public readonly bool $linear = false)
    {
        AlterationInvariant::expression($expression);
    }
}
