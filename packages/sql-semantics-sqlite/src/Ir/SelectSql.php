<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\From\Join;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Order;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Ir\Rows\RecursiveDefinition;
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
        $sql = $source instanceof LocalSource ? $this->localPrefix($source) : '';
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
     * Emits the closed definition and its generated local name.
     */
    public function localPrefix(LocalSource $source): string
    {
        $names = [];
        foreach ($source->columns as $name) {
            $names[] = $name->quoted();
        }
        $with = 'WITH ';
        if ($source->query instanceof RecursiveDefinition) {
            $definition = $source->query;
            $with = 'WITH RECURSIVE ';
            $operands = [];
            foreach ($definition->step as $column) {
                $operands[] = $this->expression($column->expression, $source);
            }
            $query = $definition->anchor->toString() . ($definition->unionAll ? ' UNION ALL SELECT ' : ' UNION SELECT ')
                . implode(', ', $operands) . ' FROM ' . $this->source($source, 0);
            if ($definition->where !== null) {
                $query .= ' WHERE ' . $this->expression($definition->where, $source);
            }
        } else {
            $query = $source->query->toString();
        }

        return $with . self::CTE_NAME . ' (' . implode(', ', $names) . ') AS (' . $query . ') ';
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
        Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|ColumnReference|Comparison|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|RealLiteral|TextLiteral|TruthTest|UnaryExpression $expression,
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
            $expression instanceof BetweenTest => $this->between($expression, $source, $joins),
            $expression instanceof FoldedMembership => $this->foldedMembership($expression),
            $expression instanceof MembershipTest => $this->membership($expression, $source, $joins),
            $expression instanceof CaseExpression => $this->choice($expression, $source, $joins),
            $expression instanceof CastExpression => '(CAST(' . $this->expression($expression->operand, $source, $joins) . ' AS ' . $expression->target->value . '))',
            $expression instanceof TruthTest => $this->truth($expression, $source, $joins),
            $expression instanceof UnaryExpression => '(' . $expression->kind->value . ' ' . $this->expression($expression->operand, $source, $joins) . ')',
            $expression instanceof IntegerLiteral => $expression->decimal,
            $expression instanceof HexIntegerLiteral => ($expression->negated ? '-' : '') . '0x' . $expression->digits,
            $expression instanceof RealLiteral => $expression->toString(),
            $expression instanceof TextLiteral => "'" . str_replace("'", "''", $expression->value) . "'",
            $expression instanceof BlobLiteral => "X'" . $expression->hex . "'",
            $expression instanceof NullLiteral => 'NULL',
            $expression instanceof Parameter => '?' . $expression->label,
        };
    }

    /**
     * Emits the decided value while registering every numbered marker in order.
     */
    public function foldedMembership(FoldedMembership $expression): string
    {
        if ($expression->bindings === []) {
            return $expression->matches ? '1' : '0';
        }
        $branches = [];
        foreach ($expression->bindings as $binding) {
            $branches[] = ' WHEN ?' . $binding->label . ' THEN 0';
        }

        return '((CASE' . implode('', $branches) . ' END)' . ($expression->matches ? ' NOT IN ())' : ' IN ())');
    }

    /**
     * Emits membership syntax and visits every written candidate in order.
     * @param list<Join> $joins The remaining source occurrences
     */
    public function membership(MembershipTest $expression, TableSource|LocalSource|null $source, array $joins): string
    {
        $operand = $this->expression($expression->operand, $source, $joins);
        $members = [];
        foreach ($expression->items as $item) {
            $members[] = $this->expression($item->expression, $source, $joins);
        }

        return '(' . $operand . ($expression->negated ? ' NOT IN (' : ' IN (') . implode(', ', $members) . '))';
    }

    /**
     * Emits one tested scalar and its ordered inclusive bounds.
     * @param list<Join> $joins The remaining source occurrences
     */
    public function between(BetweenTest $expression, TableSource|LocalSource|null $source, array $joins): string
    {
        return '(' . $this->expression($expression->operand, $source, $joins)
            . ($expression->negated ? ' NOT BETWEEN ' : ' BETWEEN ')
            . $this->expression($expression->lower, $source, $joins) . ' AND '
            . $this->expression($expression->upper, $source, $joins) . ')';
    }

    /**
     * Emits a Boolean test whose result is independent of visible truth-word names.
     * @param list<Join> $joins The subsequent input occurrences
     */
    public function truth(TruthTest $expression, TableSource|LocalSource|null $source, array $joins): string
    {
        $value = $this->expression($expression->operand, $source, $joins);
        $condition = $expression->desired ? $value : '(NOT ' . $value . ')';
        [$matched, $unmatched] = $expression->negated ? ['0', '1'] : ['1', '0'];

        return '(CASE WHEN ' . $condition . ' THEN ' . $matched . ' ELSE ' . $unmatched . ' END)';
    }

    /**
     * Emits the native conditional form and evaluates the base at most once.
     *
     * @param list<Join> $joins The remaining source occurrences
     */
    public function choice(CaseExpression $expression, TableSource|LocalSource|null $source, array $joins): string
    {
        $sql = '(CASE';
        if ($expression->base !== null) {
            $sql .= ' ' . $this->expression($expression->base, $source, $joins);
        }
        foreach ($expression->branches as $branch) {
            $sql .= ' WHEN ' . $this->expression($branch->when, $source, $joins)
                . ' THEN ' . $this->expression($branch->then, $source, $joins);
        }
        if ($expression->otherwise !== null) {
            $sql .= ' ELSE ' . $this->expression($expression->otherwise, $source, $joins);
        }

        return $sql . ' END)';
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
