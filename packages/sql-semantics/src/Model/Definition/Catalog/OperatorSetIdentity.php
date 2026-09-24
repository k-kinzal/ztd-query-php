<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog;

use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An operator class or operator family addressed by its name and its index access method.
 * @visibility public
 * @example Addressing an operator family
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY app.ints USING btree SET SCHEMA archive');
 *     $statement->object instanceof \SqlSemantics\Model\Definition\Catalog\OperatorSetIdentity // => true
 *     $statement->object->method // => 'btree'
 */
final class OperatorSetIdentity implements ObjectAddress
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Kind\OperatorSetKind $kind, public readonly QualifiedName $name, public readonly string $method)
    {
        CatalogInvariant::name($name, 2);
        CatalogInvariant::identifier($method);
    }
}
