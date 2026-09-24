<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Column;

use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Selects the TOAST storage strategy of one column.
 * @visibility public
 * @example Reading the strategy
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id SET STORAGE main');
 *     $statement->actions[0]->storage // => \SqlSemantics\Model\Definition\Relation\Column\ColumnStorageMode::Main
 */
final class SetColumnStorage implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly ColumnStorageMode $storage)
    {
        ColumnInvariant::column($column);
    }
}
