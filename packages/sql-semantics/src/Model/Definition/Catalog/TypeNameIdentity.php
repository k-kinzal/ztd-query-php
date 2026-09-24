<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog;

use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A type or domain addressed by its catalog name rather than by a type declaration.
 * Renaming, ownership, and schema changes address types this way.
 * @visibility public
 * @example Addressing a domain by name
 *     $object = new \SqlSemantics\Model\Definition\Catalog\TypeNameIdentity(\SqlSemantics\Model\Definition\Catalog\Kind\TypeKind::Domain, new \SqlSemantics\Model\Relation\QualifiedName(['app', 'money']));
 *     $object->name->parts // => ['app', 'money']
 */
final class TypeNameIdentity implements ObjectAddress
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Kind\TypeKind $kind, public readonly QualifiedName $name)
    {
        CatalogInvariant::name($name, 2);
    }
}
