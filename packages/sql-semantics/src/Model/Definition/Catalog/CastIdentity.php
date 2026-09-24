<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A cast addressed by its source and target types.
 * @visibility public
 * @example Addressing a cast
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('COMMENT ON CAST (integer AS text) IS NULL');
 *     $statement->object instanceof \SqlSemantics\Model\Definition\Catalog\CastIdentity // => true
 *     $statement->object->target->name // => 'text'
 */
final class CastIdentity implements ObjectAddress
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly TypeDescriptor $source, public readonly TypeDescriptor $target)
    {
        if ($source->dialect !== Dialect::PostgreSql || $target->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A cast address requires PostgreSQL type declarations.');
        }
    }
}
