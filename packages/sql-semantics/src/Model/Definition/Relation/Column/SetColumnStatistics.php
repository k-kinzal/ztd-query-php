<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Column;

use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Sets the per-column statistics target; index expression columns are addressed by position.
 * A target of -1 restores the system default, which the DEFAULT keyword also selects.
 * @visibility public
 * @example Addressing an index column by position
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER INDEX ix ALTER COLUMN 2 SET STATISTICS 500');
 *     $statement->actions[0]->column // => 2
 *     $statement->actions[0]->target // => 500
 * @example Rejecting a target below the default marker
 *     new \SqlSemantics\Model\Definition\Relation\Column\SetColumnStatistics('id', -2); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SetColumnStatistics implements RelationAction
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string|int $column, public readonly int $target)
    {
        if (is_string($column)) {
            ColumnInvariant::column($column);
        } elseif ($column < 1 || $column > 32767) {
            throw new InvalidStructure('A column position is between 1 and 32767.');
        }
        if ($target < -1 || $target > 10000) {
            throw new InvalidStructure('A statistics target is between -1 and 10000.');
        }
    }
}
