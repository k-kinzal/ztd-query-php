<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Emits SQL from a complete PostgreSQL SELECT.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class SelectSql
{
    private const SOURCE_ALIAS = '"__ztd_source"';

    /**
     * Renders the result columns in order, then the optional source.
     */
    public function render(Select $select): string
    {
        $source = $select->source;
        $items = [];
        foreach ($select->columns as $column) {
            $items[] = $this->expression($column->expression, $source) . ' AS ' . $column->name->quoted();
        }
        $sql = 'SELECT ' . implode(', ', $items);
        if ($source !== null) {
            $sql .= ' FROM ' . ($source->only ? 'ONLY ' : '') . $source->schema->quoted() . '.' . $source->table->quoted() . ' AS ' . self::SOURCE_ALIAS;
        }

        return $sql;
    }

    /**
     * Renders a checked expression occurrence.
     */
    public function expression(Arithmetic|ColumnReference|IntegerLiteral|BooleanLiteral|NullLiteral $expression, ?TableSource $source): string
    {
        if ($expression instanceof ColumnReference) {
            Check::invariant(isset($source->columns[$expression->slot]), 'A complete SELECT has this source slot.');

            return self::SOURCE_ALIAS . '.' . $source->columns[$expression->slot]->quoted();
        }

        return match (true) {
            $expression instanceof Arithmetic => '(' . $this->expression($expression->left, $source) . ' OPERATOR(pg_catalog.' . $expression->operator->value . ') ' . $this->expression($expression->right, $source) . ')',
            $expression instanceof IntegerLiteral => 'CAST(' . $expression->decimal . ' AS pg_catalog.int4)',
            $expression instanceof NullLiteral => 'NULL',
            $expression instanceof BooleanLiteral => $expression->value ? 'TRUE' : 'FALSE',
        };
    }
}
