<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Window\Windowing;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\OrderingAggregates;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;

/**
 * Answers the base column each output column of a query block reads, as the column metadata of its result reports it.
 *
 * A select item that is a window function is a column of the temporary table of its window. A
 * select item of a block WITH ROLLUP that is a grouping expression reads no base column, but in
 * MySQL 5.6 and 5.7 (verified on live 5.6.51 and 5.7.44 servers); when the rows pass through a
 * temporary table, for ORDER BY or DISTINCT, it is a column of that table.
 * The columns of a block with SQL_BUFFER_RESULT, or with DISTINCT over more than one table that is
 * not constant ({@see ConstantTables}), are of no key, unless the server finds at once that the
 * block reads no row (verified on a live 8.4 server).
 *
 * @visibility MySqlMemory
 */
final class Origins
{
    /**
     * @param Planner $planner The planner of the statement
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Answers the base column each output column of a block reads: none for a rolled-up item,
     * unless the rows pass through a temporary table, and of no key when the result is buffered.
     *
     * @param list<Field> $fields The fields of the select list
     * @param list<bool> $rolled Whether each item is a grouping expression of a block WITH ROLLUP
     * @param list<Domain> $domains The domains of the select list
     * @param bool $sorted Whether the block sorts its rows
     * @return list<ColumnOrigin|null>
     */
    public function origins(Select $select, ?Scope $outer, Scope $scope, array $fields, array $rolled, array $domains, bool $sorted): array
    {
        $buffered = ($this->aggregateOrder($select) || in_array(SelectOption::BufferResult, $select->options, true) || (in_array(SelectOption::Distinct, $select->options, true) && (new ConstantTables($this->planner->compiler->facts))->joined($select) > 1)) && !$this->planner->blocks->empty($select, $outer);
        $materialized = $sorted || in_array(SelectOption::Distinct, $select->options, true);
        $implicit = $this->planner->compiler->settings->legacy() && $select->groupBy === null && (new Grouping($this->planner))->collect($select) !== [];
        $supplied = $implicit ? $this->supplied($select->from) : [];
        $origins = array_map(fn (Field $field, bool $rolled, Domain $domain): ?ColumnOrigin => match (true) {
            $field->expression !== null && Windowing::windowed($field->expression) => $this->planner->materialized([$domain])[0],
            $rolled => $materialized ? $this->planner->materialized([$domain])[0] : ($this->planner->compiler->settings->legacy() ? $this->origin($field, $scope) : null),
            $implicit => $this->kept($field, $scope, $this->origin($field, $scope), $supplied),
            default => $this->origin($field, $scope),
        }, $fields, $rolled, $domains);

        return $buffered ? array_map(static fn (?ColumnOrigin $origin): ?ColumnOrigin => $origin?->unkeyed(), $origins) : $origins;
    }

    /**
     * Tells whether sorting an explicit group by its aggregates buffers the result.
     *
     * Such a result preserves column identities but loses key flags. Ordinary column
     * ordering retains those flags. Verified through PDO on MySQL 5.6, 8.0 and 8.4.
     */
    public function aggregateOrder(Select $select): bool
    {
        if ($select->groupBy === null) {
            return false;
        }
        $grouping = new Grouping($this->planner);
        $owned = array_fill_keys(array_map(spl_object_id(...), $grouping->collect($select)), true);
        foreach ([...$select->orderBy, ...($select->late->orderBy ?? [])] as $item) {
            if ((new OrderingAggregates())->occurrences($grouping->target($item->expression), $owned) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the origin of a column a block that aggregates without GROUP BY reads directly in MySQL 5.6 or 5.7, flagged NOT NULL when the column is in the relation it reads, though the value can be NULL there, unless an outer join supplies NULL for the relation (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @param list<int> $supplied The object ids of the relations an outer join supplies NULL for
     */
    public function kept(Field $field, Scope $scope, ?ColumnOrigin $origin, array $supplied = []): ?ColumnOrigin
    {
        $resolution = match (true) {
            $field->expression instanceof ColumnUse => $this->planner->compiler->facts->scalar($field->expression)->resolution,
            $field->expression === null => $field->resolution,
            default => null,
        };
        if ($origin === null || !$resolution instanceof ResolvedColumn || in_array(spl_object_id($resolution->relation), $supplied, true)) {
            return $origin;
        }
        $domain = $scope->columns[spl_object_id($resolution->relation)][$this->planner->compiler->names->position($scope, $resolution)] ?? null;

        return $domain === null || $domain->nullable ? $origin : new ColumnOrigin($origin->schema, $origin->table, $origin->originalTable, $origin->column, $origin->flags | \MySqlMemory\Result\ColumnFlag::NotNull->value, $origin->exact);
    }

    /**
     * Answers the object ids of the relations an outer join in a FROM clause supplies NULL for: those on the side of the join it does not keep.
     *
     * @return list<int>
     */
    public function supplied(?\SqlSemantics\Statement\Relation $from): array
    {
        $supplied = [];
        foreach ($from === null ? [] : (new Walker())->find($from, \SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable::class, false) as $join) {
            $sides = [...($join->operator->keepsLeft() && !$join->operator->keepsRight() ? [$join->right] : []), ...($join->operator->keepsRight() && !$join->operator->keepsLeft() ? [$join->left] : [])];
            foreach ($sides as $side) {
                foreach ([$side, ...(new Walker())->find($side, \SqlSemantics\Statement\Relation::class, false)] as $relation) {
                    $supplied[] = spl_object_id($relation);
                }
            }
        }

        return array_values(array_unique($supplied));
    }

    /**
     * Answers the base column an output column reads directly, if any; DEFAULT(column) reports the column it reads, a variable of a stored program carries the flags of a column of its type, without BINARY unless it is a string, and a BLOB or TEXT result of a stored function is flagged BLOB (verified on a live 8.4 server).
     */
    public function origin(Field $field, Scope $scope): ?ColumnOrigin
    {
        $replacement = $field->expression === null ? null : $this->planner->compiler->facts->scalar($field->expression)->replacement;
        if ($replacement !== null) {
            return $this->origin(new Field($field->position, $field->slot, $replacement), $scope);
        }
        $resolution = $field->resolution;
        if ($field->expression instanceof ColumnUse) {
            $resolution = $this->planner->compiler->facts->scalar($field->expression)->resolution;
        }
        if ($field->expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn) {
            $resolution = $this->planner->compiler->facts->scalar($field->expression->column)->resolution;
        }
        if ((new ProgramColumns($this->planner))->called($field)) {
            return (new ProgramColumns($this->planner))->flagged($field, false);
        }
        if (!$resolution instanceof ResolvedColumn) {
            return null;
        }
        $id = spl_object_id($resolution->relation);
        if (isset($scope->derived[$id])) {
            $position = $this->planner->compiler->names->position($scope, $resolution);

            $inner = $scope->merged[$id][$position] ?? null;

            return new ColumnOrigin($inner->schema ?? '', $scope->derived[$id], $inner->originalTable ?? '', $scope->names[$id][$position] ?? '', $inner->flags ?? 0);
        }
        if (!isset($scope->tables[$id])) {
            return $field->expression instanceof ColumnUse && $this->planner->compiler->connection->program !== null && $scope->locate($resolution->relation) === null ? (new ProgramColumns($this->planner))->flagged($field, true) : null;
        }
        $definition = $scope->tables[$id];
        $position = $this->planner->compiler->names->position($scope, $resolution);
        $alias = $resolution->relation instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference && $resolution->relation->alias !== null ? $resolution->relation->alias->value : $definition->name;
        $system = $this->planner->dictionary->system?->origin($definition, $position, $alias);
        if ($system !== null) {
            return $system;
        }

        return new ColumnOrigin($definition->schema, $alias, $definition->name, $definition->columns[$position]->name, $definition->flags($position));
    }
}
