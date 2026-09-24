<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Column;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Changes the declared type of one column, optionally with a collation and a conversion expression.
 * @visibility public
 * @example Reading a conversion
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id SET DATA TYPE text COLLATE "C" USING id::text');
 *     $statement->actions[0]->type->name // => 'text'
 *     $statement->actions[0]->collation->parts // => ['C']
 *     $statement->actions[0]->using instanceof \SqlSemantics\Model\Expression // => true
 */
final class ColumnTypeChange implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly TypeDescriptor $type, public readonly ?QualifiedName $collation = null, public readonly ?Expression $using = null)
    {
        ColumnInvariant::column($column);
        if ($type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A column type change requires a PostgreSQL type declaration.');
        }
        if ($collation !== null) {
            CatalogInvariant::name($collation, 2);
        }
        ColumnInvariant::expression($using);
    }
}
