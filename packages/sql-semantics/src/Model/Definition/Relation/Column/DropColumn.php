<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Column;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes one column with a policy for objects that depend on it.
 * @visibility public
 * @example Reading a tolerant cascading removal
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t DROP COLUMN IF EXISTS id CASCADE');
 *     $statement->actions[0]->column // => 'id'
 *     $statement->actions[0]->behavior // => \SqlSemantics\Model\Definition\DropBehavior::Cascade
 */
final class DropColumn implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly bool $ifExists = false, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        CatalogInvariant::identifier($column);
    }
}
