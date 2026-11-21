<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\From\Join;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Order;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Emits a complete SQLite SELECT with canonical occurrence aliases.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SelectSql
{
    private const SOURCE_ALIAS = '"__ztd_source"';

    private const CTE_NAME = '"__ztd_cte"';

    /**
     * Renders the SELECT, the join chain, the filter and the ordering in clause order.
     */
    public function render(Select $select): string
    {
        $source = $select->source;
        $sql = '';
        if ($source instanceof LocalSource) {
            $names = [];
            foreach ($source->columns as $name) {
                $names[] = $name->quoted();
            }
            $sql = 'WITH ' . self::CTE_NAME . ' (' . implode(', ', $names) . ') AS (' . $source->query->toString() . ') ';
        }
        $items = [];
        foreach ($select->columns as $column) {
            $items[] = $this->expression($column->expression, $source, $select->joins) . ' AS ' . $column->name->quoted();
        }
        $sql .= 'SELECT ' . ($select->distinct ? 'DISTINCT ' : '') . implode(', ', $items);
        if ($source !== null) {
            $sql .= ' FROM ' . $this->source($source, 0);
        }
        foreach ($select->joins as $index => $join) {
            $sql .= ' ' . $join->kind->value . ' JOIN ' . $this->source($join->source, $index + 1);
            if ($join->on !== null) {
                $sql .= ' ON ' . $this->expression($join->on, $source, $select->joins);
            }
        }
        if ($select->where !== null) {
            $sql .= ' WHERE ' . $this->expression($select->where, $source, $select->joins);
        }
        return $sql . $this->ordering($select->orderBy) . ($select->limit === null ? '' : ' ' . $this->limit($select->limit));
    }

    /**
     * Emits ordinal ordering for a projection or compound result.
     *
     * @param list<Order> $orderBy
     */
    public function ordering(array $orderBy): string
    {
        if ($orderBy === []) {
            return '';
        }
        $terms = [];
        foreach ($orderBy as $term) {
            $terms[] = $term->position . ($term->descending ? ' DESC' : ' ASC') . ($term->nullsFirst ? ' NULLS FIRST' : ' NULLS LAST');
        }

        return ' ORDER BY ' . implode(', ', $terms);
    }

    /**
     * Emits the row bounds in the recorded operand order.
     */
    public function limit(Limit $limit): string
    {
        $count = $this->expression($limit->count, null);
        if ($limit->offset === null) {
            return 'LIMIT ' . $count;
        }

        $offset = $this->expression($limit->offset, null);

        return $limit->offsetFirst ? 'LIMIT ' . $offset . ', ' . $count : 'LIMIT ' . $count . ' OFFSET ' . $offset;
    }

    /**
     * Renders a checked expression occurrence, keeping its operand order.
     *
     * @param list<Join> $joins The remaining source occurrences
     */
    public function expression(
        Arithmetic|BlobLiteral|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral $expression,
        TableSource|LocalSource|null $source,
        array $joins = [],
    ): string {
        if ($expression instanceof ColumnReference) {
            $source = $expression->source === 0 ? $source : ($joins[$expression->source - 1]->source ?? null);
            Check::invariant(isset($source->columns[$expression->slot]), 'A complete SELECT has this source slot.');

            return $this->alias($expression->source) . '.' . $source->columns[$expression->slot]->quoted();
        }

        return match (true) {
            $expression instanceof Arithmetic, $expression instanceof Comparison, $expression instanceof Logical
                => '(' . $this->expression($expression->left, $source, $joins) . ' ' . $expression->operator->value . ' ' . $this->expression($expression->right, $source, $joins) . ')',
            $expression instanceof IntegerLiteral => $expression->decimal,
            $expression instanceof HexIntegerLiteral => ($expression->negated ? '-' : '') . '0x' . $expression->digits,
            $expression instanceof RealLiteral => ($expression->negative ? '-' : '') . $expression->whole
                . ($expression->fraction === null ? '' : '.' . $expression->fraction)
                . ($expression->exponent === null ? '' : 'e' . $expression->exponent),
            $expression instanceof TextLiteral => "'" . str_replace("'", "''", $expression->value) . "'",
            $expression instanceof BlobLiteral => "X'" . $expression->hex . "'",
            $expression instanceof NullLiteral => 'NULL',
            $expression instanceof Parameter => '?' . $expression->label,
        };
    }

    /**
     * Renders a local or absolute external reference with its occurrence alias.
     */
    public function source(TableSource|LocalSource $source, int $occurrence): string
    {
        $name = $source instanceof LocalSource ? self::CTE_NAME : $source->schema->quoted() . '.' . $source->table->quoted();

        return $name . ' AS ' . $this->alias($occurrence);
    }

    /**
     * Gives each occurrence its own quoted name.
     */
    public function alias(int $occurrence): string
    {
        return $occurrence === 0 ? self::SOURCE_ALIAS : '"__ztd_source_' . $occurrence . '"';
    }
}
