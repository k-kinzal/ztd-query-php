<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog;

use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A database-wide or cluster-wide object addressed by one unqualified name.
 * @visibility public
 * @example Addressing an extension
 *     $object = new \SqlSemantics\Model\Definition\Catalog\NamedIdentity(\SqlSemantics\Model\Definition\Catalog\Kind\NamedObjectKind::Extension, 'postgis');
 *     $object->name // => 'postgis'
 * @example Rejecting an empty name
 *     new \SqlSemantics\Model\Definition\Catalog\NamedIdentity(\SqlSemantics\Model\Definition\Catalog\Kind\NamedObjectKind::Schema, ''); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class NamedIdentity implements ObjectAddress
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Kind\NamedObjectKind $kind, public readonly string $name)
    {
        CatalogInvariant::identifier($name);
    }
}
