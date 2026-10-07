<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\OutputSlot;
use UnitEnum;

/**
 * Finds the first column a grouped or DISTINCT query block reads that its rows do not determine.
 *
 * Rule: MYSQL-ONLY-FULL-GROUP-BY-001. A block with GROUP BY determines the
 * columns it groups by, the columns WHERE or an inner join compares with a
 * value that reads no column of the block, the columns compared equal to a
 * determined column, and every column of a table occurrence whose primary
 * key, or unique key of NOT NULL columns, is determined. A select item or
 * ORDER BY key that is a GROUP BY expression is determined; any other one
 * must read only determined columns outside aggregates, else the first
 * column that is not is reported (GroupingRule::NotDetermined). A block that
 * aggregates without GROUP BY reports the first select item column outside
 * an aggregate (WithoutGroupBy). A DISTINCT block reports the first ORDER BY
 * column that no select item is (NotSelected). The server checks this after
 * it resolves the block, only when the statement resolved without problem
 * so far, and only under ONLY_FULL_GROUP_BY. Terminates: the
 * determined set only grows and is bounded by the columns of the block.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/group-by-handling.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class GroupedColumns
{
    /**
     * Reports the first column of a block that breaks ONLY_FULL_GROUP_BY.
     *
     * @param list<VisibleRelation> $visible The relations of the FROM clause of the block
     */
    public function check(Select $select, array $visible, Derivation $derivation): void
    {
        $facts = $derivation->facts();
        if ($facts->diagnostics !== []) {
            return;
        }
        $relations = [];
        foreach ($visible as $relation) {
            $relations[spl_object_id($relation->relation)] = $relation;
        }
        $aggregates = (new Aggregation())->aggregates(array_map(static fn (object $item): object => $item instanceof SelectExpression ? $item->expression : $item, $select->items));
        $problem = null;
        if ($select->groupBy !== null) {
            $problem = $this->grouped($select, $relations, $facts, $derivation);
        } elseif ($aggregates || ($select->having !== null && (new Aggregation())->aggregates([$select->having]))) {
            $problem = $this->items($select, $relations, $facts, null, GroupingRule::WithoutGroupBy, $derivation);
        } elseif (in_array(SelectOption::Distinct, $select->options, true)) {
            $problem = $this->distinct($select, $relations, $facts, $derivation);
        }
        if ($problem !== null) {
            $derivation->report($problem);
        }
    }

    /**
     * Checks the select items and ORDER BY keys of a block with GROUP BY.
     *
     * @param array<int, VisibleRelation> $relations
     */
    public function grouped(Select $select, array $relations, Facts $facts, Derivation $derivation): ?NonGroupedColumn
    {
        $groups = [];
        $determined = [];
        foreach ($select->groupBy?->items ?? [] as $item) {
            $expression = $this->target($item->expression, $facts);
            $groups[] = $expression;
            foreach ($this->columns($expression, $relations, $facts, false) as [$key]) {
                if ($this->unwrap($expression) instanceof ColumnUse) {
                    $determined[$key] = true;
                }
            }
        }
        $determined = $this->closure($select, $relations, $facts, $determined);
        $problem = $this->items($select, $relations, $facts, [$groups, $determined], GroupingRule::NotDetermined, $derivation);
        if ($problem !== null) {
            return $problem;
        }
        foreach ($select->orderBy as $position => $item) {
            $expression = $this->target($item->expression, $facts);
            $missing = $this->undetermined($expression, $groups, $determined, $relations, $facts);
            if ($missing !== null) {
                return new NonGroupedColumn(GroupingRule::NotDetermined, true, $position + 1, $this->name($missing, $relations, $derivation));
            }
        }

        return null;
    }

    /**
     * Checks the select items: with a determination for GROUP BY, or that no column is read outside an aggregate.
     *
     * @param array<int, VisibleRelation> $relations
     * @param array{list<Scalar>, array<string, true>}|null $grouping The GROUP BY expressions and determined columns
     */
    public function items(Select $select, array $relations, Facts $facts, ?array $grouping, GroupingRule $rule, Derivation $derivation): ?NonGroupedColumn
    {
        foreach ($select->items as $position => $item) {
            if (!$item instanceof SelectExpression) {
                $missing = $grouping === null ? $this->firstStar($relations) : null;
            } else {
                $missing = $grouping === null ? ($this->columns($item->expression, $relations, $facts, false)[0][1] ?? null) : $this->undetermined($item->expression, $grouping[0], $grouping[1], $relations, $facts);
            }
            if ($missing !== null) {
                return new NonGroupedColumn($rule, false, $position + 1, $this->name($missing, $relations, $derivation));
            }
        }

        return null;
    }

    /**
     * Checks that every ORDER BY column of a DISTINCT block is a select item.
     *
     * @param array<int, VisibleRelation> $relations
     */
    public function distinct(Select $select, array $relations, Facts $facts, Derivation $derivation): ?NonGroupedColumn
    {
        $selected = [];
        $expressions = [];
        foreach ($select->items as $item) {
            if ($item instanceof SelectExpression) {
                $expressions[] = $item->expression;
                foreach ($this->columns($item->expression, $relations, $facts, true) as [$key]) {
                    if ($this->unwrap($item->expression) instanceof ColumnUse) {
                        $selected[$key] = true;
                    }
                }
            } else {
                return null;
            }
        }
        foreach ($select->orderBy as $position => $item) {
            $expression = $this->target($item->expression, $facts);
            if ($this->listed($expression, $expressions)) {
                continue;
            }
            foreach ($this->columns($expression, $relations, $facts, true) as [$key, $column]) {
                if (!isset($selected[$key])) {
                    return new NonGroupedColumn(GroupingRule::NotSelected, true, $position + 1, $this->name($column, $relations, $derivation));
                }
            }
        }

        return null;
    }

    /**
     * Answers the first column an expression reads outside aggregates that the grouping does not determine, unless it is a GROUP BY expression.
     *
     * @param list<Scalar> $groups
     * @param array<string, true> $determined
     * @param array<int, VisibleRelation> $relations
     */
    public function undetermined(Scalar $expression, array $groups, array $determined, array $relations, Facts $facts): ?ResolvedColumn
    {
        if ($this->listed($expression, $groups)) {
            return null;
        }
        foreach ($this->columns($expression, $relations, $facts, false) as [$key, $column]) {
            if (!isset($determined[$key])) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Tells whether an expression is written as one of a list of expressions.
     *
     * @param list<Scalar> $expressions
     */
    public function listed(Scalar $expression, array $expressions): bool
    {
        foreach ($expressions as $candidate) {
            if ($this->same($this->unwrap($expression), $this->unwrap($candidate))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether two parts of a statement are written alike: same classes and values, column names without regard to case.
     */
    public function same(mixed $left, mixed $right): bool
    {
        if (is_array($left) && is_array($right)) {
            if (count($left) !== count($right)) {
                return false;
            }
            foreach ($left as $index => $value) {
                if (!array_key_exists($index, $right) || !$this->same($value, $right[$index])) {
                    return false;
                }
            }

            return true;
        }
        if (is_object($left) && is_object($right)) {
            if ($left::class !== $right::class) {
                return false;
            }
            if ($left instanceof UnitEnum) {
                return $left === $right;
            }
            if ($left instanceof \SqlSemantics\Statement\Identifier\Name && $right instanceof \SqlSemantics\Statement\Identifier\Name) {
                return strcasecmp($left->value, $right->value) === 0;
            }

            return $this->same(get_object_vars($left), get_object_vars($right));
        }

        return $left === $right;
    }

    /**
     * Answers the expression a GROUP BY or ORDER BY key reads: the select item an alias or a position names, or the key itself.
     */
    public function target(Scalar $key, Facts $facts): Scalar
    {
        $node = $this->unwrap($key);
        if ($facts->covers($node)) {
            $resolution = $facts->scalar($node)->resolution;
            if ($resolution instanceof AliasTarget && $resolution->field->expression !== null) {
                return $resolution->field->expression;
            }
        }

        return $key;
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

    /**
     * Answers the columns of the block an expression reads, with their keys, outside aggregates unless asked to look into them.
     *
     * @param array<int, VisibleRelation> $relations
     * @return list<array{string, ResolvedColumn}>
     */
    public function columns(mixed $value, array $relations, Facts $facts, bool $aggregated): array
    {
        if (is_array($value)) {
            $found = [];
            foreach ($value as $item) {
                array_push($found, ...$this->columns($item, $relations, $facts, $aggregated));
            }

            return $found;
        }
        if (!$value instanceof Node || (!$aggregated && ($value instanceof Aggregate && $value->over === null || $value instanceof GroupConcat && $value->over === null || $value instanceof JsonObjectAggregate))) {
            return [];
        }
        if ($value instanceof ColumnUse && $facts->covers($value)) {
            $resolution = $facts->scalar($value)->resolution;
            if ($resolution instanceof AliasTarget) {
                return $resolution->field->expression === null ? [] : $this->columns($resolution->field->expression, $relations, $facts, $aggregated);
            }

            return $resolution instanceof ResolvedColumn && isset($relations[spl_object_id($resolution->relation)]) ? [[$this->key($resolution), $resolution]] : [];
        }
        $found = [];
        foreach (get_object_vars($value) as $property) {
            array_push($found, ...$this->columns($property, $relations, $facts, $aggregated));
        }

        return $found;
    }

    /**
     * Answers the key of the column a resolution names, the same for every use of one column of one occurrence.
     */
    public function key(ResolvedColumn $resolution): string
    {
        $slot = $resolution->slot;
        while ($slot->origin instanceof OutputSlot && $slot->column === null) {
            $slot = $slot->origin;
        }

        return spl_object_id($resolution->relation) . ':' . spl_object_id($slot->declaration() ?? $slot);
    }

    /**
     * Extends a determined set by the comparisons of WHERE and inner joins and by the keys of the tables, to its fixed point.
     *
     * @param array<int, VisibleRelation> $relations
     * @param array<string, true> $determined
     * @return array<string, true>
     */
    public function closure(Select $select, array $relations, Facts $facts, array $determined): array
    {
        $equalities = [];
        $conjuncts = [...$this->conjuncts($select->where), ...$this->joinConditions($select->from)];
        foreach ($conjuncts as $conjunct) {
            if (!$conjunct instanceof Comparison || ($conjunct->operator !== ComparisonOperator::Equal && $conjunct->operator !== ComparisonOperator::NullSafeEqual)) {
                continue;
            }
            $left = $this->columns($conjunct->left, $relations, $facts, true);
            $right = $this->columns($conjunct->right, $relations, $facts, true);
            if ($left === [] && $this->unwrap($conjunct->right) instanceof ColumnUse && count($right) === 1) {
                $determined[$right[0][0]] = true;
            } elseif ($right === [] && $this->unwrap($conjunct->left) instanceof ColumnUse && count($left) === 1) {
                $determined[$left[0][0]] = true;
            } elseif (count($left) === 1 && count($right) === 1 && $this->unwrap($conjunct->left) instanceof ColumnUse && $this->unwrap($conjunct->right) instanceof ColumnUse) {
                $equalities[] = [$left[0][0], $right[0][0]];
            }
        }
        do {
            $before = count($determined);
            foreach ($equalities as [$one, $two]) {
                if (isset($determined[$one]) || isset($determined[$two])) {
                    $determined[$one] = $determined[$two] = true;
                }
            }
            $determined = $this->keyed($relations, $facts, $determined);
        } while (count($determined) > $before);

        return $determined;
    }

    /**
     * Adds every column of a table occurrence whose determining key is determined.
     *
     * @param array<int, VisibleRelation> $relations
     * @param array<string, true> $determined
     * @return array<string, true>
     */
    public function keyed(array $relations, Facts $facts, array $determined): array
    {
        foreach ($relations as $id => $relation) {
            $table = $relation->relation instanceof TableReference && $facts->covers($relation->relation) ? $facts->relation($relation->relation)->table : null;
            if (!$table instanceof DeclaredTable) {
                continue;
            }
            foreach ($table->table->keys as $key) {
                $complete = $key->determines();
                foreach ($key->columns as $column) {
                    $complete = $complete && isset($determined[$id . ':' . spl_object_id($column)]);
                }
                if ($complete) {
                    foreach ([...$table->table->columns, ...array_map(static fn ($implicit) => $implicit->column, $table->table->implicit)] as $column) {
                        $determined[$id . ':' . spl_object_id($column)] = true;
                    }
                }
            }
        }

        return $determined;
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
    public function joinConditions(mixed $relation): array
    {
        if ($relation instanceof JoinedTable) {
            $conditions = [...$this->joinConditions($relation->left), ...$this->joinConditions($relation->right)];

            return !$relation->operator->keepsLeft() && !$relation->operator->keepsRight() ? [...$conditions, ...$this->conjuncts($relation->on)] : $conditions;
        }
        if ($relation instanceof Node) {
            $conditions = [];
            foreach (get_object_vars($relation) as $property) {
                foreach (is_array($property) ? $property : [] as $member) {
                    array_push($conditions, ...$this->joinConditions($member));
                }
            }

            return $conditions;
        }

        return [];
    }

    /**
     * Answers the first column a star reads, for an aggregated block without GROUP BY.
     *
     * @param array<int, VisibleRelation> $relations
     */
    public function firstStar(array $relations): ?ResolvedColumn
    {
        foreach ($relations as $relation) {
            foreach ($relation->shape->slots as $slot) {
                return new ResolvedColumn($relation->relation, $slot, 0);
            }
        }

        return null;
    }

    /**
     * Names a column as the server does: database, table name or alias, and column.
     *
     * @param array<int, VisibleRelation> $relations
     */
    public function name(ResolvedColumn $column, array $relations, Derivation $derivation): string
    {
        $relation = $relations[spl_object_id($column->relation)] ?? null;
        $table = $relation?->alias?->value ?? $relation?->name?->name->value ?? '';
        $schema = $relation?->name === null ? null : ($relation->name->schema?->value ?? ($derivation->context->searchPath[0]->value ?? null));
        $name = $column->slot->name?->value ?? '';

        return ($schema === null ? '' : $schema . '.') . $table . '.' . $name;
    }
}
