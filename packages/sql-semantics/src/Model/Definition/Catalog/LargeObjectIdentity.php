<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog;

use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A large object addressed by its object identifier.
 * @visibility public
 * @example Addressing a large object
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('COMMENT ON LARGE OBJECT 16384 IS NULL');
 *     $statement->object instanceof \SqlSemantics\Model\Definition\Catalog\LargeObjectIdentity // => true
 *     $statement->object->id // => 16384
 * @example Rejecting a negative identifier
 *     new \SqlSemantics\Model\Definition\Catalog\LargeObjectIdentity(-1); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class LargeObjectIdentity implements ObjectAddress
{
    /**
     * Object identifiers are unsigned 32-bit integers.
     * @throws InvalidStructure
     */
    public function __construct(public readonly int $id)
    {
        if ($id < 0 || $id > 4294967295) {
            throw new InvalidStructure('A large object identifier must be an unsigned 32-bit integer.');
        }
    }
}
