<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Grouping;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Query\Aggregation;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
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
 * must read only determined columns outside aggregates and the arguments
 * of GROUPING(), else the first column that is not is reported
 * (GroupingRule::NotDetermined). A block WITH ROLLUP determines only the
 * columns it groups by, since its super-aggregate rows set them to NULL
 * (verified on a live 8.4 server). A block that
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
     * @param list<Field|OpenStar> $fields The output fields of the select list, stars expanded
     */
    public function check(Select $select, array $visible, array $fields, Derivation $derivation): void
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
            $problem = $this->grouped($select, $fields, $relations, $facts, $derivation);
        } elseif ($aggregates || ($select->having !== null && (new Aggregation())->aggregates([$select->having]))) {
            $problem = $this->items($fields, $relations, $facts, null, GroupingRule::WithoutGroupBy, $derivation);
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
     * @param list<Field|OpenStar> $fields
     * @param array<int, VisibleRelation> $relations
     */
    public function grouped(Select $select, array $fields, array $relations, Facts $facts, Derivation $derivation): ?NonGroupedColumn
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
        if ($select->groupBy?->modifier === null) {
            $determined = $this->closure($select, $relations, $facts, $determined);
        }
        $problem = $this->items($fields, $relations, $facts, [$groups, $determined], GroupingRule::NotDetermined, $derivation);
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
     * Checks the output fields, a star contributing one field per column and the fields numbered as the server numbers them: with a determination for GROUP BY, or that no column is read outside an aggregate.
     *
     * @param list<Field|OpenStar> $fields
     * @param array<int, VisibleRelation> $relations
     * @param array{list<Scalar>, array<string, true>}|null $grouping The GROUP BY expressions and determined columns
     */
    public function items(array $fields, array $relations, Facts $facts, ?array $grouping, GroupingRule $rule, Derivation $derivation): ?NonGroupedColumn
    {
        foreach ($fields as $field) {
            if (!$field instanceof Field) {
                return null;
            }
            if ($field->expression !== null) {
                $missing = $grouping === null ? ($this->columns($field->expression, $relations, $facts, false)[0][1] ?? null) : $this->undetermined($field->expression, $grouping[0], $grouping[1], $relations, $facts);
            } else {
                $column = $field->resolution instanceof ResolvedColumn && isset($relations[spl_object_id($field->resolution->relation)]) ? $field->resolution : null;
                $missing = $column !== null && ($grouping === null || !isset($grouping[1][$this->key($column)])) ? $column : null;
            }
            if ($missing !== null) {
                return new NonGroupedColumn($rule, false, $field->position + 1, $this->name($missing, $relations, $derivation));
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
            if ($this->listed($expression, $expressions, $facts)) {
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
        if ($this->listed($expression, $groups, $facts)) {
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
    public function listed(Scalar $expression, array $expressions, ?Facts $facts = null): bool
    {
        foreach ($expressions as $candidate) {
            if ($this->same($this->unwrap($expression), $this->unwrap($candidate), $facts)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether two parts of a statement are written alike: same classes and values, column names without regard to case, and with facts two column names that resolve to the same column of the same occurrence however they are qualified (`t.a + 1` is `a + 1`, verified on a live 8.4 server).
     */
    public function same(mixed $left, mixed $right, ?Facts $facts = null): bool
    {
        if (is_array($left) && is_array($right)) {
            if (count($left) !== count($right)) {
                return false;
            }
            foreach ($left as $index => $value) {
                if (!array_key_exists($index, $right) || !$this->same($value, $right[$index], $facts)) {
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
            if ($left instanceof Name && $right instanceof Name) {
                return strcasecmp($left->value, $right->value) === 0;
            }
            if ($facts !== null && $left instanceof ColumnUse && $right instanceof ColumnUse && $facts->covers($left) && $facts->covers($right)) {
                $first = $facts->scalar($left)->resolution;
                $second = $facts->scalar($right)->resolution;
                if ($first instanceof ResolvedColumn && $second instanceof ResolvedColumn) {
                    return $this->key($first) === $this->key($second);
                }
            }

            return $this->same(get_object_vars($left), get_object_vars($right), $facts);
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
        if (!$value instanceof Node || (!$aggregated && ($value instanceof Aggregate && $value->over === null || $value instanceof GroupConcat && $value->over === null || $value instanceof JsonObjectAggregate || $value instanceof KeywordCall && $value->function === KeywordFunction::Grouping))) {
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
     * Extends a determined set by the equalities of WHERE and of the joins, by the keys of the tables and by the dependencies inside derived tables, to its fixed point.
     *
     * @param array<int, VisibleRelation> $relations
     * @param array<string, true> $determined
     * @param array<int, true> $within The query blocks of derived tables whose dependencies are being found, which are not entered again
     * @return array<string, true>
     */
    public function closure(Select $select, array $relations, Facts $facts, array $determined, array $within = []): array
    {
        $dependencies = [];
        foreach ($this->conjuncts($select->where) as $conjunct) {
            [$determined, $dependencies] = $this->equality($conjunct, $relations, $facts, null, $determined, $dependencies);
        }
        foreach ($this->joins($select->from) as $join) {
            [$determined, $dependencies] = $this->joined($join, $relations, $facts, $determined, $dependencies);
        }
        do {
            $before = count($determined);
            foreach ($dependencies as [$from, $to]) {
                if (isset($determined[$from])) {
                    $determined[$to] = true;
                }
            }
            $determined = $this->keyed($relations, $facts, $determined, $within);
        } while (count($determined) > $before);

        return $determined;
    }

    /**
     * Records what an equality determines: a column compared with a value that reads no column of the block, and each of two compared columns by the other; for the condition of an outer join, only columns of its inner side, by a value or by a column of its other side.
     *
     * @param array<int, VisibleRelation> $relations
     * @param array<int, VisibleRelation>|null $inner The occurrences of the inner side of an outer join, or null for WHERE and inner joins
     * @param array<string, true> $determined
     * @param list<array{string, string}> $dependencies The pairs of columns where the first determines the second
     * @return array{array<string, true>, list<array{string, string}>}
     */
    public function equality(Scalar $conjunct, array $relations, Facts $facts, ?array $inner, array $determined, array $dependencies): array
    {
        if (!$conjunct instanceof Comparison || ($conjunct->operator !== ComparisonOperator::Equal && $conjunct->operator !== ComparisonOperator::NullSafeEqual)) {
            return [$determined, $dependencies];
        }
        $sides = [];
        foreach ([$conjunct->left, $conjunct->right] as $side) {
            $columns = $this->columns($side, $relations, $facts, true);
            $sides[] = [$columns, $this->unwrap($side) instanceof ColumnUse && count($columns) === 1 ? $columns[0] : null];
        }
        foreach ([[$sides[0], $sides[1]], [$sides[1], $sides[0]]] as [[$read, $from], [, $to]]) {
            if ($to === null || ($inner !== null && !isset($inner[spl_object_id($to[1]->relation)]))) {
                continue;
            }
            if ($read === []) {
                $determined[$to[0]] = true;
            } elseif ($from !== null && ($inner === null || !isset($inner[spl_object_id($from[1]->relation)]))) {
                $dependencies[] = [$from[0], $to[0]];
            }
        }

        return [$determined, $dependencies];
    }

    /**
     * Records what the condition of a join determines: the equalities of ON, and the columns USING or NATURAL compares, each by the other; an outer join determines only columns of its inner side.
     *
     * @param array<int, VisibleRelation> $relations
     * @param array<string, true> $determined
     * @param list<array{string, string}> $dependencies The pairs of columns where the first determines the second
     * @return array{array<string, true>, list<array{string, string}>}
     */
    public function joined(JoinedTable $join, array $relations, Facts $facts, array $determined, array $dependencies): array
    {
        $left = $this->members($join->left, $relations);
        $right = $this->members($join->right, $relations);
        $inner = $join->operator->keepsLeft() ? $right : ($join->operator->keepsRight() ? $left : null);
        foreach ($this->conjuncts($join->on) as $conjunct) {
            [$determined, $dependencies] = $this->equality($conjunct, $relations, $facts, $inner, $determined, $dependencies);
        }
        $names = array_map(static fn (Name $name): string => mb_strtolower($name->value), $join->using);
        if ($join->operator->natural()) {
            $names = array_keys(array_intersect_key($this->named($left), $this->named($right)));
        }
        $first = $this->named($left);
        $second = $this->named($right);
        foreach ($names as $name) {
            foreach ($first[$name] ?? [] as $one) {
                foreach ($second[$name] ?? [] as $two) {
                    if (!$join->operator->keepsRight()) {
                        $dependencies[] = [$one, $two];
                    }
                    if (!$join->operator->keepsLeft()) {
                        $dependencies[] = [$two, $one];
                    }
                }
            }
        }

        return [$determined, $dependencies];
    }

    /**
     * Answers the keys of the columns of some occurrences by their lowercase names.
     *
     * @param array<int, VisibleRelation> $relations
     * @return array<string, list<string>>
     */
    public function named(array $relations): array
    {
        $named = [];
        foreach ($relations as $relation) {
            foreach ($relation->shape->slots as $slot) {
                if ($slot->name !== null) {
                    $named[mb_strtolower($slot->name->value)][] = $this->key(new ResolvedColumn($relation->relation, $slot));
                }
            }
        }

        return $named;
    }

    /**
     * Answers the occurrences of a part of a FROM clause, among the given ones.
     *
     * @param array<int, VisibleRelation> $relations
     * @return array<int, VisibleRelation>
     */
    public function members(Relation $part, array $relations): array
    {
        $members = [];
        foreach ($this->leaves($part) as $leaf) {
            if (isset($relations[spl_object_id($leaf)])) {
                $members[spl_object_id($leaf)] = $relations[spl_object_id($leaf)];
            }
        }

        return $members;
    }

    /**
     * Adds every column of a table occurrence whose determining key is determined, and the columns a derived table determines.
     *
     * @param array<int, VisibleRelation> $relations
     * @param array<string, true> $determined
     * @param array<int, true> $within The query blocks of derived tables whose dependencies are being found
     * @return array<string, true>
     */
    public function keyed(array $relations, Facts $facts, array $determined, array $within = []): array
    {
        foreach ($relations as $id => $relation) {
            $table = $relation->relation instanceof TableReference && $facts->covers($relation->relation) ? $facts->relation($relation->relation)->table : null;
            if (!$table instanceof DeclaredTable) {
                $determined = $this->derived($relation, $facts, $determined, $within);

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
     * Adds the columns of a derived table or a common table that its determined columns determine inside its query block.
     *
     * Inside a block without a grouping modifier, the determined columns
     * determine the columns of the block they read, closed as the outer
     * block is; a field is then determined when it reads only determined
     * columns outside aggregates (a window function, RAND() and a subquery
     * that reads no column of the block are determined too, verified on a
     * live 8.4 server), and every field is once the GROUP BY expressions
     * of the block are.
     *
     * @param array<string, true> $determined
     * @param array<int, true> $within The query blocks of derived tables whose dependencies are being found
     * @return array<string, true>
     */
    public function derived(VisibleRelation $relation, Facts $facts, array $determined, array $within): array
    {
        $select = $this->block($relation->relation, $facts);
        if ($select === null || isset($within[spl_object_id($select)]) || $select->groupBy?->modifier !== null || !$facts->covers($select)) {
            return $determined;
        }
        $fields = $facts->query($select)->projection;
        $inner = [];
        foreach ($this->leaves($select->from) as $leaf) {
            if ($facts->covers($leaf)) {
                $inner[spl_object_id($leaf)] = new VisibleRelation($leaf, $facts->relation($leaf)->shape);
            }
        }
        $seeds = [];
        $expressions = [];
        foreach ($relation->shape->slots as $position => $slot) {
            $field = $fields[$position] ?? null;
            if (!$field instanceof Field) {
                return $determined;
            }
            if (!isset($determined[$this->key(new ResolvedColumn($relation->relation, $slot))])) {
                continue;
            }
            if ($field->expression !== null) {
                $expressions[] = $field->expression;
                $read = $this->unwrap($field->expression) instanceof ColumnUse ? $this->columns($field->expression, $inner, $facts, true) : [];
            } else {
                $read = $field->resolution instanceof ResolvedColumn && isset($inner[spl_object_id($field->resolution->relation)]) ? [[$this->key($field->resolution), $field->resolution]] : [];
            }
            foreach ($read as [$key]) {
                $seeds[$key] = true;
            }
        }
        $closed = $this->closure($select, $inner, $facts, $seeds, [...$within, spl_object_id($select) => true]);
        $grouped = $select->groupBy !== null;
        foreach ($select->groupBy->items ?? [] as $item) {
            $expression = $this->target($item->expression, $facts);
            $grouped = $grouped && ($this->listed($expression, $expressions, $facts) || $this->undetermined($expression, [], $closed, $inner, $facts) === null);
        }
        foreach ($relation->shape->slots as $position => $slot) {
            if ($grouped || $this->depends($fields[$position], $inner, $facts, $closed)) {
                $determined[$this->key(new ResolvedColumn($relation->relation, $slot))] = true;
            }
        }

        return $determined;
    }

    /**
     * Tells whether a field of a query block reads only determined columns of the block, and none inside an aggregate.
     *
     * @param array<int, VisibleRelation> $relations
     * @param array<string, true> $determined
     */
    public function depends(object $field, array $relations, Facts $facts, array $determined): bool
    {
        if (!$field instanceof Field) {
            return false;
        }
        if ($field->expression === null) {
            return $field->resolution instanceof ResolvedColumn && isset($determined[$this->key($field->resolution)]);
        }
        if ((new Aggregation())->aggregates([$field->expression])) {
            return false;
        }
        foreach ($this->columns($field->expression, $relations, $facts, true) as [$key]) {
            if (!isset($determined[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Answers the query block a derived table or a common table reads its rows from, when it is a single block.
     */
    public function block(Relation $relation, Facts $facts): ?Select
    {
        $query = null;
        if ($relation instanceof DerivedTable) {
            $query = $relation->query;
        } elseif ($relation instanceof TableReference && $facts->covers($relation)) {
            $table = $facts->relation($relation)->table;
            $query = $table instanceof CommonTable && $table->definition instanceof CommonTableExpression ? $table->definition->query : null;
        }
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }

        return $query instanceof Select ? $query : null;
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
     * Answers the joins of a FROM clause or of a part of it, outside derived tables.
     *
     * @return list<JoinedTable>
     */
    public function joins(?Relation $relation): array
    {
        if ($relation instanceof JoinedTable) {
            return [$relation, ...$this->joins($relation->left), ...$this->joins($relation->right)];
        }
        $joins = [];
        foreach ($this->parts($relation) ?? [] as $part) {
            array_push($joins, ...$this->joins($part));
        }

        return $joins;
    }

    /**
     * Answers the table occurrences of a FROM clause or of a part of it, outside derived tables.
     *
     * @return list<Relation>
     */
    public function leaves(?Relation $relation): array
    {
        if ($relation instanceof JoinedTable) {
            return [...$this->leaves($relation->left), ...$this->leaves($relation->right)];
        }
        $parts = $this->parts($relation);
        if ($parts === null) {
            return $relation === null ? [] : [$relation];
        }
        $leaves = [];
        foreach ($parts as $part) {
            array_push($leaves, ...$this->leaves($part));
        }

        return $leaves;
    }

    /**
     * Answers the relations a list, parentheses or an escape groups, or null for a table occurrence.
     *
     * @return list<Relation>|null
     */
    public function parts(?Relation $relation): ?array
    {
        return match (true) {
            $relation instanceof TableList => $relation->members,
            $relation instanceof NestedRelation, $relation instanceof OdbcJoin, $relation instanceof EscapedRelation => [$relation->relation],
            default => null,
        };
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
