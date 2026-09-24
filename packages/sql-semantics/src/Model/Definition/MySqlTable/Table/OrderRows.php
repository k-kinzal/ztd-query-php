<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Table;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Ordering;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Stores the rows of the rebuilt table in the order of the listed columns (ORDER BY).
 * @visibility public
 * @example Reading the row order
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ORDER BY n DESC, id');
 *     $statement->alterations[0]->orderings[0]->descending // => true
 *     $statement->alterations[0]->orderings[1]->key->referenceParts() // => ['id']
 */
final class OrderRows implements TableAlteration
{
    /**
     * @param non-empty-list<Ordering> $orderings Column orderings without NULLS placement
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $orderings)
    {
        Collections::objects(Collections::nonEmpty($orderings), Ordering::class);
        foreach ($orderings as $ordering) {
            if (!$ordering->key instanceof Expression || $ordering->nullsFirst !== null) {
                throw new InvalidStructure('A table row order names columns with an optional direction.');
            }
            AlterationInvariant::expression($ordering->key);
        }
    }
}
