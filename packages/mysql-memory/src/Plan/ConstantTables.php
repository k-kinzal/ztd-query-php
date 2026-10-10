<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;

/**
 * Counts the tables a query block joins that the server does not read as constants.
 *
 * A table is constant when WHERE or the condition of an inner join compares every column of its
 * primary key, or of a unique key of NOT NULL columns, equal to a value that reads only constant
 * tables; a derived table of one query block without tables, or limited to one row, is constant
 * too. A table of the inner side of an outer join is never constant. Each EXISTS or IN subquery
 * that WHERE requires joins one more table. A block whose rows DISTINCT reads from more than one
 * table that is not constant puts them in a temporary table, whose columns are of no key
 * (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain-output.html ("const").
 *
 * @visibility MySqlMemory
 */
final class ConstantTables
{
    /**
     * @param Facts $facts The facts of the statement
     */
    public function __construct(public readonly Facts $facts)
    {
    }

    /**
     * Answers how many tables a block joins that are not constant, counting each semijoin of WHERE as one.
     */
    public function joined(Select $select): int
    {
        $tables = $this->tables($select->from, false);
        $constant = $this->constants($select, $tables);
        $count = count(array_filter($tables, static fn (array $entry): bool => !isset($constant[spl_object_id($entry[0])])));
        foreach ($this->conjuncts($select->where) as $conjunct) {
            $conjunct = $conjunct instanceof Not ? $this->unwrap($conjunct->operand) : $conjunct;
            if ($conjunct instanceof Exists || $conjunct instanceof InQuery) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Answers the tables of a FROM clause or of a part of it in written order, each with whether it is on the inner side of an outer join.
     *
     * @return list<array{Relation, bool}>
     */
    public function tables(?Relation $relation, bool $outer): array
    {
        $parts = match (true) {
            $relation instanceof JoinedTable => [[$relation->left, $outer || $relation->operator->keepsRight()], [$relation->right, $outer || $relation->operator->keepsLeft()]],
            $relation instanceof TableList => array_map(static fn (Relation $member): array => [$member, $outer], $relation->members),
            $relation instanceof NestedRelation, $relation instanceof OdbcJoin, $relation instanceof EscapedRelation => [[$relation->relation, $outer]],
            default => null,
        };
        if ($parts === null) {
            return $relation === null ? [] : [[$relation, $outer]];
        }
        $tables = [];
        foreach ($parts as [$part, $inner]) {
            array_push($tables, ...$this->tables($part, $inner));
        }

        return $tables;
    }

    /**
     * Answers the tables of a block that are constant, by object id.
     *
     * @param list<array{Relation, bool}> $tables
     * @return array<int, true>
     */
    public function constants(Select $select, array $tables): array
    {
        $constant = [];
        foreach ($tables as [$table, $outer]) {
            if (!$outer && $table instanceof DerivedTable && $this->single($table->query)) {
                $constant[spl_object_id($table)] = true;
            }
        }
        $conditions = [...$this->conjuncts($select->where), ...$this->joins($select->from)];
        $members = array_fill_keys(array_map(static fn (array $entry): int => spl_object_id($entry[0]), $tables), true);
        do {
            $before = count($constant);
            $bound = [];
            foreach ($conditions as $condition) {
                if (!$condition instanceof Comparison || ($condition->operator !== ComparisonOperator::Equal && $condition->operator !== ComparisonOperator::NullSafeEqual)) {
                    continue;
                }
                foreach ([[$condition->left, $condition->right], [$condition->right, $condition->left]] as [$column, $value]) {
                    $resolution = $this->column($column);
                    if ($resolution !== null && $this->fixed($value, $members, $constant)) {
                        $bound[spl_object_id($resolution->relation)][spl_object_id($resolution->slot->declaration() ?? $resolution->slot)] = true;
                    }
                }
            }
            foreach ($tables as [$table, $outer]) {
                if (!$outer && $table instanceof TableReference && $this->keyed($table, $bound[spl_object_id($table)] ?? [])) {
                    $constant[spl_object_id($table)] = true;
                }
            }
        } while (count($constant) > $before);

        return $constant;
    }

    /**
     * Tells whether a derived query is one query block without tables, or limited to at most one row.
     */
    public function single(Query $query): bool
    {
        $limit = null;
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression) {
            $limit ??= $query instanceof QueryExpression ? $query->limit : null;
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        if (!$query instanceof Select) {
            return false;
        }
        $limit ??= $query->limit;
        $count = $limit instanceof RowLimit && $limit->offset === null ? $this->unwrap($limit->count) : null;

        return $query->from === null || ($count instanceof NumberLiteral && (int) $count->text <= 1);
    }

    /**
     * Tells whether the bound columns of a table occurrence hold all the columns of a key that determines its rows.
     *
     * @param array<int, true> $bound The bound columns, by the object id of their declaration
     */
    public function keyed(TableReference $table, array $bound): bool
    {
        $resolution = $this->facts->covers($table) ? $this->facts->relation($table)->table : null;
        if (!$resolution instanceof DeclaredTable) {
            return false;
        }
        foreach ($resolution->table->keys as $key) {
            $complete = $key->determines() && $key->columns !== [];
            foreach ($key->columns as $column) {
                $complete = $complete && isset($bound[spl_object_id($column)]);
            }
            if ($complete) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the column of a block an expression names, when it is one.
     */
    public function column(Scalar $expression): ?ResolvedColumn
    {
        $expression = $this->unwrap($expression);
        if (!$expression instanceof ColumnUse || !$this->facts->covers($expression)) {
            return null;
        }
        $resolution = $this->facts->scalar($expression)->resolution;

        return $resolution instanceof ResolvedColumn ? $resolution : null;
    }

    /**
     * Tells whether a value reads no column of a table of the block that is not constant.
     *
     * @param array<int, true> $members The tables of the block, by object id
     * @param array<int, true> $constant The constant tables, by object id
     */
    public function fixed(Scalar $value, array $members, array $constant): bool
    {
        foreach ((new Walker())->find($value, ColumnUse::class) as $use) {
            $resolution = $this->facts->covers($use) ? $this->facts->scalar($use)->resolution : null;
            if (!$resolution instanceof ResolvedColumn) {
                return false;
            }
            $id = spl_object_id($resolution->relation);
            if (isset($members[$id]) && !isset($constant[$id])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Answers the AND operands of a condition.
     *
     * @return list<Scalar>
     */
    public function conjuncts(?Scalar $condition): array
    {
        if ($condition === null) {
            return [];
        }
        $condition = $this->unwrap($condition);
        if ($condition instanceof Logical && $condition->operator === LogicalOperator::And) {
            return [...$this->conjuncts($condition->left), ...$this->conjuncts($condition->right)];
        }

        return [$condition];
    }

    /**
     * Answers the AND operands of the conditions of the inner joins of a FROM clause.
     *
     * @return list<Scalar>
     */
    public function joins(?Relation $relation): array
    {
        $conditions = [];
        foreach ($relation === null ? [] : (new Walker())->find($relation, JoinedTable::class, false) as $join) {
            if (!$join->operator->keepsLeft() && !$join->operator->keepsRight()) {
                array_push($conditions, ...$this->conjuncts($join->on));
            }
        }

        return $conditions;
    }

    /**
     * Removes the parentheses around an expression.
     */
    public function unwrap(Scalar $expression): Scalar
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }

        return $expression;
    }
}
