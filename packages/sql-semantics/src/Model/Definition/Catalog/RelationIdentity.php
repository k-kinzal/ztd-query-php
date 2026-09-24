<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog;

use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A table, sequence, view, materialized view, index, or foreign table named without resolving it.
 * @visibility public
 * @example Addressing a materialized view
 *     $object = new \SqlSemantics\Model\Definition\Catalog\RelationIdentity(\SqlSemantics\Model\Definition\Catalog\Kind\RelationKind::MaterializedView, new \SqlSemantics\Model\Relation\QualifiedName(['app', 'totals']));
 *     $object->name->parts // => ['app', 'totals']
 * @example Rejecting an over-qualified relation name
 *     new \SqlSemantics\Model\Definition\Catalog\RelationIdentity(\SqlSemantics\Model\Definition\Catalog\Kind\RelationKind::Table, new \SqlSemantics\Model\Relation\QualifiedName(['a', 'b', 'c', 'd'])); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class RelationIdentity implements ObjectAddress
{
    /**
     * A relation name has at most a database, a schema, and the relation itself.
     * @throws InvalidStructure
     */
    public function __construct(public readonly Kind\RelationKind $kind, public readonly QualifiedName $name)
    {
        CatalogInvariant::name($name, 3);
    }
}
