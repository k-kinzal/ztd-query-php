<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Column;

use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Turns a stored generated column into an ordinary column, keeping its current values.
 * @visibility public
 * @example Reading a tolerant removal
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id DROP EXPRESSION IF EXISTS');
 *     $statement->actions[0]->ifExists // => true
 */
final class DropColumnExpression implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly bool $ifExists = false)
    {
        ColumnInvariant::column($column);
    }
}
