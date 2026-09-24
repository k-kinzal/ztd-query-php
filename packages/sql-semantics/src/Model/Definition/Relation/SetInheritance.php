<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Adds or removes a parent table in the inheritance hierarchy of this table.
 * @visibility public
 * @example Removing a parent
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t NO INHERIT app.base');
 *     $statement->actions[0]->inherit // => false
 *     $statement->actions[0]->parent->parts // => ['app', 'base']
 */
final class SetInheritance implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly QualifiedName $parent, public readonly bool $inherit)
    {
        CatalogInvariant::name($parent, 3);
    }
}
