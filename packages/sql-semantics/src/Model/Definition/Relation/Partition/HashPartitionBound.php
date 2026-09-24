<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Partition;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Selects the rows whose hash remainder modulo the modulus equals the remainder.
 * @visibility public
 * @example Reading a hash bound
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ATTACH PARTITION t_p1 FOR VALUES WITH (MODULUS 4, REMAINDER 1)');
 *     $statement->actions[0]->bound->modulus // => 4
 *     $statement->actions[0]->bound->remainder // => 1
 * @example Rejecting a remainder outside the modulus
 *     new \SqlSemantics\Model\Definition\Relation\Partition\HashPartitionBound(4, 4); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class HashPartitionBound
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly int $modulus, public readonly int $remainder)
    {
        if ($modulus < 1 || $remainder < 0 || $remainder >= $modulus) {
            throw new InvalidStructure('A hash bound requires a positive modulus and a remainder below it.');
        }
    }
}
