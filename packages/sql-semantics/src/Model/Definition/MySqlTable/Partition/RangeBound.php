<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Partition;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\Relation\Partition\RangeBoundary;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The exclusive upper bound of a RANGE partition; MAXVALUE stands for a bound above every value.
 * @visibility public
 * @example Reading a range bound
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t PARTITION BY RANGE (id) (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN MAXVALUE)');
 *     $statement->alterations[0]->partitioning->partitions[1]->values->bound // => [\SqlSemantics\Model\Definition\Relation\Partition\RangeBoundary::MaxValue]
 */
final class RangeBound
{
    /**
     * @param non-empty-list<Expression|RangeBoundary> $bound One value per partitioning column
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $bound)
    {
        Collections::alternatives(Collections::nonEmpty($bound), [Expression::class, RangeBoundary::class]);
        foreach ($bound as $value) {
            if ($value === RangeBoundary::MinValue) {
                throw new InvalidStructure('A MySQL range bound uses MAXVALUE only.');
            }
            if ($value instanceof Expression) {
                AlterationInvariant::expression($value);
            }
        }
    }
}
