<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Partition;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Selects the rows whose partition key equals one of the listed values.
 * @visibility public
 * @example Reading the listed values
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind("ALTER TABLE t ATTACH PARTITION t_a FOR VALUES IN ('a', 'b')");
 *     count($statement->actions[0]->bound->values) // => 2
 */
final class ListPartitionBound
{
    /**
     * @param non-empty-list<Expression> $values
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $values)
    {
        Collections::objects(Collections::nonEmpty($values), Expression::class);
        PartitionInvariant::expressions($values);
    }
}
