<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A transform addressed by the type it converts and the procedural language it serves.
 * @visibility public
 * @example Addressing a transform
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("COMMENT ON TRANSFORM FOR hstore LANGUAGE plpython3u IS 'x'");
 *     $statement->object instanceof \SqlSemantics\Model\Definition\Catalog\TransformIdentity // => true
 *     $statement->object->language // => 'plpython3u'
 */
final class TransformIdentity implements ObjectAddress
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly TypeDescriptor $type, public readonly string $language)
    {
        if ($type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A transform address requires a PostgreSQL type declaration.');
        }
        CatalogInvariant::identifier($language);
    }
}
