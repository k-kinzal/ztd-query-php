<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\Binding\Parameter;
use SqlSemantics\Platform\MySql\Ir\From\Join;
use SqlSemantics\Platform\MySql\Ir\Ordering\ResultSql;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Emits SQL from a complete MySQL SELECT.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SelectSql
{
    private const SOURCE_ALIAS = '`__ztd_source`';

    /**
     * Renders the result columns in order, then the optional source and filter.
     */
    public function render(Select $select): string
    {
        $source = $select->source;
        $items = [];
        foreach ($select->columns as $column) {
            $items[] = $this->expression($column->expression, $source) . ' AS ' . $column->name->quoted();
        }
        $sql = ($select->distinct ? 'SELECT DISTINCT ' : 'SELECT ') . implode(', ', $items);
        if ($source !== null) {
            $sql .= ' FROM ' . $this->from($source);
        } elseif (($select->release === GrammarRelease::MySql5651 || $select->release === GrammarRelease::MySql5744)
            && ($select->where !== null || $select->orderBy !== [] || $select->limit !== null)) {
            $sql .= ' FROM (SELECT 1) AS `__ztd_unit`';
        }
        if ($select->where !== null) {
            $sql .= ' WHERE ' . $this->predicate($select->where, $source);
        }

        return $sql . (new ResultSql())->render($select);
    }

    /**
     * Renders a checked expression occurrence.
     */
    public function expression(Arithmetic|ColumnReference|IntegerLiteral|BooleanLiteral|NullLiteral|Parameter $expression, TableSource|Join|null $source, int $offset = 0): string
    {
        if ($expression instanceof ColumnReference) {
            $inputs = $source instanceof Join ? $source->inputs() : [$source];
            $input = $inputs[$expression->occurrence] ?? null;
            Check::invariant(isset($input->columns[$expression->slot]), 'A complete SELECT has this source slot.');

            return $this->alias($offset + $expression->occurrence) . '.' . $input->columns[$expression->slot]->quoted();
        }

        return match (true) {
            $expression instanceof Arithmetic => '(' . $this->expression($expression->left, $source, $offset) . ' ' . $expression->operator->value . ' ' . $this->expression($expression->right, $source, $offset) . ')',
            $expression instanceof IntegerLiteral => $expression->decimal,
            $expression instanceof NullLiteral => 'NULL',
            $expression instanceof Parameter => $expression->toString(),
            $expression instanceof BooleanLiteral => $expression->value ? 'TRUE' : 'FALSE',
        };
    }

    /**
     * Emits a table or a fully parenthesized join tree, using its global alias offset.
     */
    public function from(TableSource|Join $source, int $offset = 0): string
    {
        if ($source instanceof TableSource) {
            return $source->schema->quoted() . '.' . $source->table->quoted() . ' AS ' . $this->alias($offset);
        }
        $rightOffset = $offset + ($source->left instanceof Join ? count($source->left->inputs()) : 1);
        $sql = '(' . $this->from($source->left, $offset) . ' ' . $source->kind->value . ' JOIN ' . $this->from($source->right, $rightOffset);
        if ($source->on !== null) {
            $sql .= ' ON ' . $this->predicate($source->on, $source, $offset);
        }

        return $sql . ')';
    }

    /**
     * Emits the typed native predicate relative to the scope that owns it.
     */
    public function predicate(BooleanLiteral|IntegerComparison|NullLiteral $condition, TableSource|Join|null $source, int $offset = 0): string
    {
        if ($condition instanceof IntegerComparison) {
            $left = $this->expression($condition->left, $source, $offset);
            $right = $this->expression($condition->right, $source, $offset);
            $operator = $condition->operator->value;

            return '(' . $left . ' ' . $operator . ' ' . $right . ')';
        }

        return $this->expression($condition, $source, $offset);
    }

    /**
     * Names the corresponding table occurrence in the emitted statement.
     */
    public function alias(int $occurrence): string
    {
        return $occurrence === 0 ? self::SOURCE_ALIAS : '`__ztd_source_' . $occurrence . '`';
    }
}
