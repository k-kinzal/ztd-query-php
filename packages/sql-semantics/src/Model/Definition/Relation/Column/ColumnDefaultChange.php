<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Column;

use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Replaces the default value expression of one column; a null default removes it.
 * @visibility public
 * @example Removing a default
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id DROP DEFAULT');
 *     $statement->actions[0]->default // => null
 * @example Setting a default
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER id SET DEFAULT 7');
 *     $statement->actions[0]->default->text // => '7'
 */
final class ColumnDefaultChange implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly ?Expression $default)
    {
        ColumnInvariant::column($column);
        ColumnInvariant::expression($default);
    }
}
