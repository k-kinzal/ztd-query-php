<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation;

use SqlSemantics\Model\Definition\RelationAction;

/**
 * Removes the OID column, the clustering index, or the typed-table binding of a table.
 * @visibility public
 * @example Detaching a typed table from its type
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t NOT OF');
 *     $statement->actions[0]->property // => \SqlSemantics\Model\Definition\Relation\RelationProperty::Type
 */
final class RemoveRelationProperty implements RelationAction
{
    /**
     * The property is the complete operand.
     */
    public function __construct(public readonly RelationProperty $property)
    {
    }
}
