<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Column;

use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Replaces the generation expression of a stored generated column.
 * @visibility public
 * @example Reading the new expression
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, twice INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN twice SET EXPRESSION AS (id * 2)');
 *     $statement->actions[0]->expression->structure()->toString() // => '("id" * 2)'
 */
final class SetColumnExpression implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly Expression $expression)
    {
        ColumnInvariant::column($column);
        ColumnInvariant::expression($expression);
    }
}
