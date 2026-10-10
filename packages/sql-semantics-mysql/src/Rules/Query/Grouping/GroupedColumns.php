<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Grouping;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Query\Aggregation;
use SqlSemantics\Platform\MySql\Statement\Call\SetFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;

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
 * so far, and only under ONLY_FULL_GROUP_BY; a GROUP BY item holding an
 * aggregate or a window function, itself or through the alias of a select
 * item, is refused before, so the block is not checked. Terminates: the
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
     * @param bool|null $ownsAggregates Resolved ownership, or null to inspect the written select list and HAVING
     */
    public function check(Select $select, array $visible, array $fields, Derivation $derivation, ?bool $ownsAggregates = null): void
    {
        $facts = $derivation->facts();
        if ($facts->diagnostics !== [] || $this->computed($select, $facts)) {
            return;
        }
        $relations = [];
        foreach ($visible as $relation) {
            $relations[spl_object_id($relation->relation)] = $relation;
        }
        $aggregates = $ownsAggregates ?? ((new Aggregation())->aggregates(array_map(static fn (object $item): object => $item instanceof SelectExpression ? $item->expression : $item, $select->items)) || ($select->having !== null && (new Aggregation())->aggregates([$select->having])));
        $problem = null;
        if ($select->groupBy !== null) {
            $problem = $this->grouped($select, $fields, $relations, $facts, $derivation);
        } elseif ($aggregates) {
            $problem = $this->items($fields, $relations, $facts, null, GroupingRule::WithoutGroupBy, $derivation);
        } elseif (in_array(SelectOption::Distinct, $select->options, true)) {
            $problem = $this->distinct($select, $relations, $facts, $derivation);
        }
        if ($problem !== null) {
            $derivation->report($problem);
        }
    }

    /**
     * Tells whether a GROUP BY item, or the select item its alias names, holds an aggregate or a window function, which the server refuses before it checks the columns.
     */
    public function computed(Select $select, Facts $facts): bool
    {
        $matching = new Matching();
        $pending = array_map(static fn ($item): Scalar => $matching->target($item->expression, $facts), $select->groupBy->items ?? []);
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
                continue;
            }
            if (!is_object($value) || $value instanceof Query) {
                continue;
            }
            if ($value instanceof SetFunction || $value instanceof WindowFunction) {
                return true;
            }
            array_push($pending, ...array_values(get_object_vars($value)));
        }

        return false;
    }

    /**
     * Checks the select items and ORDER BY keys of a block with GROUP BY.
     *
     * @param list<Field|OpenStar> $fields
     * @param array<int, VisibleRelation> $relations
     */
    public function grouped(Select $select, array $fields, array $relations, Facts $facts, Derivation $derivation): ?NonGroupedColumn
    {
        $matching = new Matching();
        $reads = new ColumnReads();
        $determination = new Determination();
        $groups = [];
        $determined = [];
        foreach ($select->groupBy->items ?? [] as $item) {
            $expression = $matching->target($item->expression, $facts);
            $groups[] = $expression;
            foreach ($reads->columns($expression, $relations, $facts, false) as [$key]) {
                if ($matching->unwrap($expression) instanceof ColumnUse) {
                    $determined[$key] = true;
                }
            }
        }
        if ($select->groupBy?->modifier === null) {
            $determined = $determination->closure($select, $relations, $facts, $determined);
        }
        $problem = $this->items($fields, $relations, $facts, [$groups, $determined], GroupingRule::NotDetermined, $derivation);
        if ($problem !== null) {
            return $problem;
        }
        foreach ($select->orderBy as $position => $item) {
            $expression = $matching->target($item->expression, $facts);
            $missing = $determination->undetermined($expression, $groups, $determined, $relations, $facts);
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
        $reads = new ColumnReads();
        $determination = new Determination();
        foreach ($fields as $field) {
            if (!$field instanceof Field) {
                return null;
            }
            if ($field->expression !== null) {
                $missing = $grouping === null ? ($reads->columns($field->expression, $relations, $facts, false)[0][1] ?? null) : $determination->undetermined($field->expression, $grouping[0], $grouping[1], $relations, $facts);
            } else {
                $column = $field->resolution instanceof ResolvedColumn && isset($relations[spl_object_id($field->resolution->relation)]) ? $field->resolution : null;
                $missing = $column !== null && ($grouping === null || !isset($grouping[1][$reads->key($column)])) ? $column : null;
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
        $matching = new Matching();
        $reads = new ColumnReads();
        $selected = [];
        $expressions = [];
        foreach ($select->items as $item) {
            if ($item instanceof SelectExpression) {
                $expressions[] = $item->expression;
                foreach ($reads->columns($item->expression, $relations, $facts, true) as [$key]) {
                    if ($matching->unwrap($item->expression) instanceof ColumnUse) {
                        $selected[$key] = true;
                    }
                }
            } else {
                return null;
            }
        }
        foreach ($select->orderBy as $position => $item) {
            $expression = $matching->target($item->expression, $facts);
            if ($matching->listed($expression, $expressions, $facts)) {
                continue;
            }
            foreach ($reads->columns($expression, $relations, $facts, true) as [$key, $column]) {
                if (!isset($selected[$key])) {
                    return new NonGroupedColumn(GroupingRule::NotSelected, true, $position + 1, $this->name($column, $relations, $derivation));
                }
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
        $table = $relation->alias->value ?? $relation->name->name->value ?? '';
        $schema = $relation?->name === null ? null : ($relation->name->schema->value ?? ($derivation->context->searchPath[0]->value ?? null));
        $name = $column->slot->name->value ?? '';

        return ($schema === null ? '' : $schema . '.') . $table . '.' . $name;
    }
}
