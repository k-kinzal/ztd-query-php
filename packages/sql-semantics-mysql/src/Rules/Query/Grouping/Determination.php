<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Grouping;

use SqlSemantics\Platform\MySql\Rules\Query\Aggregation;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;

/**
 * Finds the columns of a query block that its determined columns determine, the functional dependencies the server derives.
 *
 * For the grouping check (MYSQL-ONLY-FULL-GROUP-BY-001), a block determines the columns WHERE or
 * an inner join compares with a value that reads no column of the block, the columns compared
 * equal to a determined column, and every column of a table occurrence whose primary key, or
 * unique key of NOT NULL columns, is determined; an outer join determines only columns of its
 * inner side. A derived table or a common table determines the columns its query block derives
 * from determined columns. Terminates: the determined set only grows and is bounded by the
 * columns of the block, and a derived table is not entered again while its own dependencies are
 * being found.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/group-by-handling.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Determination
{
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
        $parts = new BlockParts();
        $dependencies = [];
        foreach ($parts->conjuncts($select->where) as $conjunct) {
            [$determined, $dependencies] = $this->equality($conjunct, $relations, $facts, null, $determined, $dependencies);
        }
        foreach ($parts->joins($select->from) as $join) {
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
            $columns = (new ColumnReads())->columns($side, $relations, $facts, true);
            $sides[] = [$columns, (new Matching())->unwrap($side) instanceof ColumnUse && count($columns) === 1 ? $columns[0] : null];
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
        $parts = new BlockParts();
        $reads = new ColumnReads();
        $left = $parts->members($join->left, $relations);
        $right = $parts->members($join->right, $relations);
        $inner = $join->operator->keepsLeft() ? $right : ($join->operator->keepsRight() ? $left : null);
        foreach ($parts->conjuncts($join->on) as $conjunct) {
            [$determined, $dependencies] = $this->equality($conjunct, $relations, $facts, $inner, $determined, $dependencies);
        }
        $names = array_map(static fn (Name $name): string => mb_strtolower($name->value), $join->using);
        if ($join->operator->natural()) {
            $names = array_keys(array_intersect_key($reads->named($left), $reads->named($right)));
        }
        $first = $reads->named($left);
        $second = $reads->named($right);
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
        $select = (new BlockParts())->block($relation->relation, $facts);
        if ($select === null || isset($within[spl_object_id($select)]) || $select->groupBy?->modifier !== null || !$facts->covers($select)) {
            return $determined;
        }
        $fields = $this->fields($relation, $facts->query($select)->projection);
        if ($fields === null) {
            return $determined;
        }
        $reads = new ColumnReads();
        $inner = (new BlockParts())->occurrences($select, $facts);
        $seeds = [];
        $expressions = [];
        foreach ($fields as $position => $field) {
            if (isset($determined[$reads->key(new ResolvedColumn($relation->relation, $relation->shape->slots[$position]))])) {
                if ($field->expression !== null) {
                    $expressions[] = $field->expression;
                }
                foreach ($this->shown($field, $inner, $facts) as [$key]) {
                    $seeds[$key] = true;
                }
            }
        }
        $closed = $this->closure($select, $inner, $facts, $seeds, [...$within, spl_object_id($select) => true]);
        $grouped = $this->grouped($select, $expressions, $closed, $inner, $facts);
        foreach ($fields as $position => $field) {
            if ($grouped || $this->depends($field, $inner, $facts, $closed)) {
                $determined[$reads->key(new ResolvedColumn($relation->relation, $relation->shape->slots[$position]))] = true;
            }
        }

        return $determined;
    }

    /**
     * Answers the fields of a query block that the columns of its derived table show, by position, or null when a column shows no field.
     *
     * @param list<Field|OpenStar> $projection The output fields of the block
     * @return array<int, Field>|null
     */
    public function fields(VisibleRelation $relation, array $projection): ?array
    {
        $fields = [];
        foreach (array_keys($relation->shape->slots) as $position) {
            $field = $projection[$position] ?? null;
            if (!$field instanceof Field) {
                return null;
            }
            $fields[$position] = $field;
        }

        return $fields;
    }

    /**
     * Answers the column of the block a field shows: the column a select item names, or that of a star; none for any other expression.
     *
     * @param array<int, VisibleRelation> $relations The occurrences of the block
     * @return list<array{string, ResolvedColumn}>
     */
    public function shown(Field $field, array $relations, Facts $facts): array
    {
        if ($field->expression !== null) {
            return (new Matching())->unwrap($field->expression) instanceof ColumnUse ? (new ColumnReads())->columns($field->expression, $relations, $facts, true) : [];
        }

        return $field->resolution instanceof ResolvedColumn && isset($relations[spl_object_id($field->resolution->relation)]) ? [[(new ColumnReads())->key($field->resolution), $field->resolution]] : [];
    }

    /**
     * Tells whether a block has GROUP BY and every GROUP BY expression is determined: written as one of the determined select items, or reading only determined columns.
     *
     * @param list<Scalar> $expressions The expressions of the determined select items
     * @param array<string, true> $determined
     * @param array<int, VisibleRelation> $relations The occurrences of the block
     */
    public function grouped(Select $select, array $expressions, array $determined, array $relations, Facts $facts): bool
    {
        if ($select->groupBy === null) {
            return false;
        }
        $matching = new Matching();
        foreach ($select->groupBy->items as $item) {
            $expression = $matching->target($item->expression, $facts);
            if (!$matching->listed($expression, $expressions, $facts) && $this->undetermined($expression, [], $determined, $relations, $facts) !== null) {
                return false;
            }
        }

        return true;
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
            return $field->resolution instanceof ResolvedColumn && isset($determined[(new ColumnReads())->key($field->resolution)]);
        }
        if ((new Aggregation())->aggregates([$field->expression])) {
            return false;
        }
        foreach ((new ColumnReads())->columns($field->expression, $relations, $facts, true) as [$key]) {
            if (!isset($determined[$key])) {
                return false;
            }
        }

        return true;
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
        if ((new Matching())->listed($expression, $groups, $facts)) {
            return null;
        }
        foreach ((new ColumnReads())->columns($expression, $relations, $facts, false) as [$key, $column]) {
            if (!isset($determined[$key])) {
                return $column;
            }
        }

        return null;
    }
}
