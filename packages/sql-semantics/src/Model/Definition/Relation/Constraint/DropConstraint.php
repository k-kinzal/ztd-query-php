<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Constraint;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes one constraint with a policy for objects that depend on it.
 * @visibility public
 * @example Reading a tolerant removal
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t DROP CONSTRAINT IF EXISTS positive RESTRICT');
 *     $statement->actions[0]->ifExists // => true
 *     $statement->actions[0]->behavior // => \SqlSemantics\Model\Definition\DropBehavior::Restrict
 */
final class DropConstraint implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly bool $ifExists = false, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        CatalogInvariant::identifier($name);
    }
}
