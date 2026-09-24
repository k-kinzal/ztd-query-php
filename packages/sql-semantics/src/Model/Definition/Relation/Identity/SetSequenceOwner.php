<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Identity;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Associates the sequence with a table column, or with none, for automatic removal.
 * @visibility public
 * @example Reading the owning column
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (OWNED BY t.id)');
 *     $statement->actions[0]->options[0]->column->parts // => ['t', 'id']
 */
final class SetSequenceOwner
{
    /**
     * A null column is the explicit OWNED BY NONE.
     * @throws InvalidStructure
     */
    public function __construct(public readonly ?QualifiedName $column)
    {
        if ($column !== null) {
            CatalogInvariant::name($column, 4);
        }
    }
}
