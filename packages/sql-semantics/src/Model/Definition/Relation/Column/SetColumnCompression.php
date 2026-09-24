<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Column;

use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Selects the compression method for future values of one column.
 * @visibility public
 * @example Reading the method
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id SET COMPRESSION lz4');
 *     $statement->actions[0]->compression // => \SqlSemantics\Model\Definition\Relation\Column\ColumnCompression::Lz4
 */
final class SetColumnCompression implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly ColumnCompression $compression)
    {
        ColumnInvariant::column($column);
    }
}
