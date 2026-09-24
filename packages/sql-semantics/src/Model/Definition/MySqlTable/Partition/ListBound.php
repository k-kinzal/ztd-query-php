<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Partition;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The value tuples a LIST partition accepts; a tuple has one value per partitioning column.
 * @visibility public
 * @example Reading list values
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t PARTITION BY LIST (id) (PARTITION p VALUES IN (1, 2))');
 *     count($statement->alterations[0]->partitioning->partitions[0]->values->tuples) // => 2
 * @example Rejecting an empty tuple
 *     new \SqlSemantics\Model\Definition\MySqlTable\Partition\ListBound([[]]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class ListBound
{
    /**
     * @param non-empty-list<non-empty-list<Expression>> $tuples Accepted tuples of equal width
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $tuples)
    {
        Collections::nonEmpty($tuples);
        foreach ($tuples as $tuple) {
            Collections::objects(Collections::nonEmpty($tuple), Expression::class);
            array_map(AlterationInvariant::expression(...), $tuple);
            if (count($tuple) !== count($tuples[0])) {
                throw new InvalidStructure('LIST partition tuples have equal width.');
            }
        }
    }
}
