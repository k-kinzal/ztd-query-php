<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds the table to a composite type whose attributes match its columns.
 * @visibility public
 * @example Reading the composite type
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t OF app.point');
 *     $statement->actions[0]->type->parts // => ['app', 'point']
 */
final class TypedTableBinding implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly QualifiedName $type)
    {
        CatalogInvariant::name($type, 2);
    }
}
