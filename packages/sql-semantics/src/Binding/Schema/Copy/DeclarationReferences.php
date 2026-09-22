<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema\Copy;

use SqlSemantics\Model\ColumnBinding;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Transformation\ExpressionEdit;
use SqlSemantics\Model\Traversal\Expressions;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableDefinition;

/**
 * Rebinds copied column references to the destination's independent symbols.
 * @visibility SqlSemantics
 */
final class DeclarationReferences
{
    /**
     * Preserves each expression's operation while changing its declaration identity.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function rebind(TableDefinition $table, TableDefinition $base): TableDefinition
    {
        $columns = array_map(static fn (ColumnDefinition $column): ColumnDefinition => new ColumnDefinition($column->name, $column->type, $column->nullability, $column->source), $table->columns);
        $symbols = new TableDefinition($table->schema, $table->name, $columns, [], $table->source, $table->resolved);
        foreach (Expressions::all($table) as $expression) {
            if (!$expression instanceof ColumnReference || $expression->binding->table->name !== $base->name || $expression->binding->table->schema !== $base->schema) {
                continue;
            }
            $matches = array_values(array_filter($columns, static fn (ColumnDefinition $column): bool => strcasecmp($column->name, $expression->binding->column->name) === 0));
            $column = $matches[0] ?? throw new \SqlSemantics\Model\Validation\InvalidStructure('A copied expression must reference a destination column.');
            $reference = new ColumnReference($expression->facts, $expression->source, new ColumnBinding('declaration', $symbols, $column), $expression->origins, [$column->name]);
            $table = ExpressionEdit::rebuild($table, $expression, $reference);
        }
        return $table;
    }
}
