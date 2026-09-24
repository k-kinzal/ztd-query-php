<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog;

use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A column, constraint, policy, rule, or trigger addressed by its own name and the relation that owns it.
 * @visibility public
 * @example Addressing a column of a qualified table
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("COMMENT ON COLUMN app.users.email IS 'contact'");
 *     $statement->object instanceof \SqlSemantics\Model\Definition\Catalog\RelationMemberIdentity // => true
 *     $statement->object->name // => 'email'
 *     $statement->object->relation->parts // => ['app', 'users']
 */
final class RelationMemberIdentity implements ObjectAddress
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Kind\RelationMemberKind $kind, public readonly string $name, public readonly QualifiedName $relation)
    {
        CatalogInvariant::identifier($name);
        CatalogInvariant::name($relation, 3);
    }
}
