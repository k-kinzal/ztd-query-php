<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Identity;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes the identity property of one column, keeping the column itself.
 * @visibility public
 * @example Reading a tolerant removal
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id DROP IDENTITY IF EXISTS');
 *     $statement->actions[0]->ifExists // => true
 */
final class DropColumnIdentity implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly bool $ifExists = false)
    {
        CatalogInvariant::identifier($column);
    }
}
