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
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;

/**
 * Answers the base column each output column of a query block reads, as the column metadata of its result reports it.
 *
 * A materialized window function is a column of its temporary table. A
 * stored assignment over a window also exposes the temporary table name. Aggregate
 * parents retain an expression origin unless DISTINCT stores them; deferred ROLLUP
 * parents remain expressions (verified through PDO on MySQL 8.0, 8.4 and 9.1). A
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
     * @param bool $storedWindows Whether window-dependent outputs use temporary columns
     * @return list<ColumnOrigin|null>
     */
    public function origins(Select $select, ?Scope $outer, Scope $scope, array $fields, array $rolled, array $domains, bool $sorted, bool $storedWindows): array
    {
        $buffered = ($this->aggregateOrder($select) || $this->joinedOrder($select) || in_array(SelectOption::BufferResult, $select->options, true) || (in_array(SelectOption::Distinct, $select->options, true) && (new ConstantTables($this->planner->compiler->facts))->joined($select) > 1)) && !$this->planner->blocks->empty($select, $outer);
        $materialized = $sorted || in_array(SelectOption::Distinct, $select->options, true);
        $implicit = $this->planner->compiler->settings->legacy() && $select->groupBy === null && (new Grouping($this->planner))->collect($select) !== [];
        $supplied = $implicit ? $this->supplied($select->from) : [];
        $origins = array_map(fn (Field $field, bool $rolled, Domain $domain): ?ColumnOrigin => match (true) {
            $this->storedAssignment($select, $field, $storedWindows) => new ColumnOrigin('', '<temporary>', '', '', $this->planner->materialized([$domain])[0]->flags ?? 0),
            $storedWindows && $field->expression !== null && (new Windowing($this->planner))->reads($field->expression) => $this->planner->materialized([$domain])[0],
            $this->storedAggregate($select, $field, $domain) => $this->planner->materialized([$domain])[0],
            $rolled => $materialized ? $this->planner->materialized([$domain])[0] : $this->rollup($select, $field, $scope),
            $implicit => $this->kept($field, $scope, $this->origin($field, $scope), $supplied),
            default => $this->origin($field, $scope),
        }, $fields, $rolled, $domains);

        return $buffered ? array_map(static fn (?ColumnOrigin $origin): ?ColumnOrigin => $origin?->unkeyed(), $origins) : $origins;
    }

    /**
     * Preserves legacy base flags for a direct ROLLUP column, except constant table lookups.
     * MySQL 5.7 retains base flags when the constant lookup finds no row.
     */
    public function rollup(Select $select, Field $field, Scope $scope): ?ColumnOrigin
    {
        if (!$this->planner->settings->legacy()) {
            return null;
        }
        $scan = $select->from === null ? null : ($scope->scans[spl_object_id($select->from)] ?? null);
        $oldest = $this->planner->settings->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651;
        $lookup = $scan !== null && (new Lookup\PrimaryKeys($this->planner))->plan($select, $scan, $scope, runtime: $oldest) instanceof Path\Source\KeyLookup;
        if ($lookup && ($oldest || !(new Grouping\Preparation($this->planner))->missing($select, $scope, new \MySqlMemory\Evaluation\Frame($this->planner->compiler->connection->context)))) {
            return null;
        }

        return $this->kept($field, $scope, $this->origin($field, $scope), $this->supplied($select->from));
    }

    /**
     * Identifies assignment fields that report a temporary table origin.
     * MySQL 8.0 also names stored ordinary aggregate assignments beside a window;
     * later releases name only assignments that themselves read window outputs.
     */
    public function storedAssignment(Select $select, Field $field, bool $storedWindows): bool
    {
        $expression = $field->expression;
        if (!$storedWindows || $expression === null || !(new \SqlSemantics\Platform\MySql\Rules\Query\Grouping\Matching())->unwrap($expression) instanceof \SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment) {
            return false;
        }
        $distinct = in_array(SelectOption::Distinct, $select->options, true);
        $named = (new Windowing($this->planner))->reads($expression) || ($this->planner->settings->release() === \SqlSemantics\Contract\GrammarRelease::MySql8044 && ($select->groupBy?->modifier === null || $distinct));

        return $named && (!(new \SqlSemantics\Platform\MySql\Rules\Query\Aggregation())->aggregates([$expression]) || ($distinct && !(new \SqlSemantics\Platform\MySql\Rules\Query\Result\OutputFields())->deferred($select, $expression, $this->planner->compiler->facts)));
    }

    /**
     * Tells whether an aggregate result exposes the BLOB flag of a stored output field.
     * Computed parents and direct ROLLUP retain expression flags until materialized.
     */
    public function storedAggregate(Select $select, Field $field, Domain $domain): bool
    {
        return $domain->field->blob() && $field->expression !== null && (new \SqlSemantics\Platform\MySql\Rules\Query\Aggregation())->aggregates([$field->expression]) && ($select->groupBy !== null || in_array(SelectOption::Distinct, $select->options, true)) && (new \SqlSemantics\Platform\MySql\Rules\Query\Result\OutputFields())->stored($select, $field->expression, $this->planner->compiler->facts, $this->planner->settings->release());
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
     * Detects ordering across independent joined rows, whose buffered result loses key flags.
     *
     * Keys from a unique lookup dependent on the first table do not introduce another
     * independent ordering dimension. Constant tables do not contribute one either.
     * Verified through SQL on MySQL 8.0, 8.4 and 9.1.
     */
    public function joinedOrder(Select $select): bool
    {
        $facts = $this->planner->compiler->facts;
        $dependencies = new ConstantTables($facts);
        $tables = $dependencies->tables($select->from, false);
        $constant = $dependencies->constants($select, $tables);
        $varying = array_values(array_filter($tables, static fn (array $table): bool => !isset($constant[spl_object_id($table[0])])));
        if (count($varying) < 2) {
            return false;
        }
        $ordered = [];
        $grouping = new Grouping($this->planner);
        foreach ([...$select->orderBy, ...($select->late->orderBy ?? [])] as $item) {
            foreach ((new Walker())->find($grouping->target($item->expression), ColumnUse::class) as $use) {
                $resolution = $facts->covers($use) ? $facts->scalar($use)->resolution : null;
                if ($resolution instanceof ResolvedColumn && !isset($constant[spl_object_id($resolution->relation)])) {
                    $ordered[spl_object_id($resolution->relation)] = true;
                }
            }
        }
        if (count($ordered) < 2) {
            return false;
        }
        $first = spl_object_id($varying[0][0]);
        $dependent = $dependencies->constants($select, $tables, $constant + [$first => true]);

        return array_key_first($ordered) !== $first || array_diff_key($ordered, $dependent) !== [];
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
            $inner = $field->expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery ? ($this->planner->compiler->facts->query($field->expression->query)->projection[0] ?? null) : null;

            return $this->origin($inner instanceof Field ? $inner : new Field($field->position, $field->slot, $replacement), $scope);
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
        if ($resolution instanceof AliasTarget) {
            return $this->origin($resolution->field, $scope);
        }
        if (!$resolution instanceof ResolvedColumn) {
            return null;
        }

        return $this->resolved($resolution, $field, $scope);
    }

    /**
     * Retains the source names and flags of a bound input column.
     */
    public function resolved(ResolvedColumn $resolution, Field $field, Scope $scope): ?ColumnOrigin
    {
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
        $alias = $resolution->relation instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference ? ($resolution->relation->alias ?? $resolution->relation->name->name)->value : $definition->name;
        $system = $this->planner->dictionary->system?->origin($definition, $position, $alias);
        if ($system !== null) {
            return $system;
        }

        return new ColumnOrigin($definition->schema, $alias, $definition->name, $definition->columns[$position]->name, $definition->flags($position));
    }
}
