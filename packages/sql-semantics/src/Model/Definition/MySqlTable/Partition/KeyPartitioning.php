<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Partition;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Assigns rows by the server hash of columns; an empty column list uses the primary key, and ALGORITHM selects hashing 1 or 2.
 * @visibility public
 * @example Reading a key function
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t PARTITION BY KEY ALGORITHM = 2 (id)');
 *     [$statement->alterations[0]->partitioning->function->columns, $statement->alterations[0]->partitioning->function->algorithm] // => [['id'], 2]
 * @example Rejecting an unknown algorithm
 *     new \SqlSemantics\Model\Definition\MySqlTable\Partition\KeyPartitioning(['id'], false, 3); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class KeyPartitioning implements PartitionFunction
{
    /**
     * @param list<string> $columns Hashed columns, empty for the primary key
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $columns, public readonly bool $linear = false, public readonly ?int $algorithm = null)
    {
        Collections::strings($columns);
        if ($columns !== []) {
            AlterationInvariant::names($columns);
        }
        if ($algorithm !== null && !in_array($algorithm, [1, 2], true)) {
            throw new InvalidStructure('KEY partitioning ALGORITHM is 1 or 2.');
        }
    }
}
