<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Having;

use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\LookupLevel;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Shape\Field;

/**
 * Resolves a name against the GROUP BY columns and the select list of a grouped row.
 *
 * Rule: MYSQL-HAVING-REFERENCE-001, after resolve_ref_in_select_and_group,
 * find_field_in_group_list (sql/item.cc) and find_item_in_list
 * (sql/sql_base.cc). A name in HAVING outside set functions, and a name of a
 * nested query that reaches the HAVING position outwards, is searched first
 * among the GROUP BY items that are columns and then in the select list; a
 * GROUP BY column wins over a select list item. In GROUP BY a column
 * matches by its column name, and a qualified name also by the occurrence
 * the column belongs to: a column of another occurrence ends the search
 * without a match, and two equally good columns that differ are ambiguous.
 * In the select list an unqualified name matches the first item that is
 * not a column and is named so, else the column items named so (two
 * different ones are ambiguous), else the column items whose column has
 * the name under another alias (likewise); a qualified name matches only
 * column items of an occurrence it admits. An item that is not a column
 * and whose name depends on missing inputs (OutputSlot::$unnamed) may be
 * the item named so: the outcome is conditional. A column item resolves to its
 * column, another item to the item (an alias target). Names of columns are
 * compared by the context's column comparison. Terminates: one pass over
 * each list.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html ("MySQL
 * permits HAVING to refer to columns in the SELECT list and columns in
 * outer subqueries"), https://dev.mysql.com/doc/refman/8.4/en/group-by-handling.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ResultReferences
{
    /**
     * Resolves a name at a HAVING position of a query that lies a number of queries outwards; null when neither list has it.
     */
    public function find(GroupedRow $row, Environment $scope, Name $column, ?QualifiedName $qualifier, int $depth): ?Resolution
    {
        return $this->grouping($row, $scope, $column, $qualifier, $depth) ?? $this->selected($row, $scope, $column, $qualifier, $depth);
    }

    /**
     * Searches the GROUP BY columns; null when none has the name.
     */
    public function grouping(GroupedRow $row, Environment $scope, Name $column, ?QualifiedName $qualifier, int $depth): ?Resolution
    {
        $best = null;
        $degree = 0;
        foreach ($row->grouping as $item) {
            $resolution = $item->resolution;
            if (!$resolution instanceof ResolvedColumn || !$this->named($scope, $resolution, $column)) {
                continue;
            }
            if ($qualifier !== null && !$this->admitted($scope, $resolution, $column, $qualifier)) {
                return null;
            }
            $current = $qualifier === null ? 1 : ($qualifier->schema === null ? 2 : 3);
            if ($best !== null && $current === $degree && !$this->same($best, $resolution)) {
                return new AmbiguousColumn($column, [$this->deeper($best, $depth), $this->deeper($resolution, $depth)]);
            }
            if ($current > $degree) {
                [$best, $degree] = [$resolution, $current];
            }
        }

        return $best === null ? null : $this->deeper($best, $depth);
    }

    /**
     * Searches the select list; null when no item has the name.
     */
    public function selected(GroupedRow $row, Environment $scope, Name $column, ?QualifiedName $qualifier, int $depth): ?Resolution
    {
        [$aliased, $unaliased, $other] = [null, null, null];
        $unnamed = $this->unnamed($row, $scope, $column, $qualifier);
        foreach ($row->selected as $item) {
            $field = $item instanceof Field ? $item : null;
            $resolution = $field?->expression instanceof ColumnUse ? $field->resolution : null;
            $named = $qualifier === null && $field?->name !== null && $scope->context->columnNames->equal($field->name->value, $column->value);
            if ($field !== null && $named && !$resolution instanceof ResolvedColumn) {
                return $this->undecided($column, new AliasTarget($field), $unnamed);
            }
            if (!$resolution instanceof ResolvedColumn) {
                continue;
            }
            if ($named) {
                if ($aliased !== null && !$this->same($aliased, $resolution)) {
                    return new AmbiguousColumn($column, [$this->deeper($aliased, $depth), $this->deeper($resolution, $depth)]);
                }
                $aliased = $resolution;
            } elseif ($this->unaliased($scope, $resolution, $column, $qualifier)) {
                $other = $other ?? ($unaliased !== null && !$this->same($unaliased, $resolution) ? $resolution : null);
                $unaliased = $unaliased ?? $resolution;
            }
        }

        return $this->undecided($column, $this->chosen($column, $aliased, $unaliased, $other, $depth), $unnamed);
    }

    /**
     * Answers a select list match, or the conditional outcome while items whose names depend on missing inputs could be the match instead.
     *
     * @param list<MissingInput> $unnamed The inputs the names of those items depend on
     */
    public function undecided(Name $column, ?Resolution $match, array $unnamed): ?Resolution
    {
        if ($unnamed === []) {
            return $match;
        }

        return new ConditionalColumn($column, $match instanceof ResolvedColumn ? [$match] : [], [], $unnamed);
    }

    /**
     * Answers the inputs the names of the items that are not columns depend on, up to the first such item an unqualified name names.
     *
     * @return list<MissingInput>
     */
    public function unnamed(GroupedRow $row, Environment $scope, Name $column, ?QualifiedName $qualifier): array
    {
        $missing = [];
        foreach ($qualifier === null ? $row->selected : [] as $item) {
            if (!$item instanceof Field || ($item->expression instanceof ColumnUse && $item->resolution instanceof ResolvedColumn)) {
                continue;
            }
            if ($item->name !== null && $scope->context->columnNames->equal($item->name->value, $column->value)) {
                break;
            }
            array_push($missing, ...$item->slot->unnamed);
        }

        return $missing;
    }

    /**
     * Tells whether a column item of the select list matches a name by its column rather than by its alias.
     */
    public function unaliased(Environment $scope, ResolvedColumn $resolution, Name $column, ?QualifiedName $qualifier): bool
    {
        return $this->named($scope, $resolution, $column) && ($qualifier === null || $this->admitted($scope, $resolution, $column, $qualifier));
    }

    /**
     * Chooses among the select list matches: the item matched by its alias, else the only column matched by its own name.
     */
    public function chosen(Name $column, ?ResolvedColumn $aliased, ?ResolvedColumn $unaliased, ?ResolvedColumn $other, int $depth): ?Resolution
    {
        if ($aliased === null && $unaliased !== null && $other !== null) {
            return new AmbiguousColumn($column, [$this->deeper($unaliased, $depth), $this->deeper($other, $depth)]);
        }
        $chosen = $aliased ?? $unaliased;

        return $chosen === null ? null : $this->deeper($chosen, $depth);
    }

    /**
     * Tells whether the column of a resolution has a name.
     */
    public function named(Environment $scope, ResolvedColumn $resolution, Name $column): bool
    {
        return $resolution->slot->name !== null && $scope->context->columnNames->equal($resolution->slot->name->value, $column->value);
    }

    /**
     * Tells whether a qualified name finds a resolved column in the occurrences of a position.
     */
    public function admitted(Environment $scope, ResolvedColumn $resolution, Name $column, QualifiedName $qualifier): bool
    {
        foreach ((new LookupLevel($scope, $column, $qualifier, 0))->found() as $found) {
            if ($this->same($found, $resolution)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether two resolutions denote the same column of the same occurrence.
     */
    public function same(ResolvedColumn $left, ResolvedColumn $right): bool
    {
        return $left->relation === $right->relation && $left->slot === $right->slot;
    }

    /**
     * Answers a resolution as seen from a use a number of queries further in.
     */
    public function deeper(ResolvedColumn $resolution, int $depth): ResolvedColumn
    {
        return $depth === 0 ? $resolution : new ResolvedColumn($resolution->relation, $resolution->slot, $resolution->depth + $depth);
    }
}
