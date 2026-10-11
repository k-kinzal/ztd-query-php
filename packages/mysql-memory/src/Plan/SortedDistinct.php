<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Dictionary\Key;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHint;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintAction;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintScope;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;

/**
 * Types the stored sort columns of DISTINCT after its access path is selected.
 *
 * An ordered BIT result is stored as an integer when duplicate removal needs
 * a temporary result. An index that covers the result, or a selected non-null
 * unique key that makes duplicate removal unnecessary, retains the BIT field.
 * LIMIT 0 retains the original metadata. Verified through SQL on MySQL 8.4.
 *
 * @visibility MySqlMemory
 */
final class SortedDistinct
{
    /**
     * @param Planner $planner The planner of the query block
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Selects temporary hash keys when DISTINCT neither sorts nor reads a covering index.
     *
     * @param list<array{int, Domain, bool}> $keys The projected sort positions
     */
    public function hashes(QueryPlan $plan, Select $select, array $keys): bool
    {
        return !$this->planner->settings->legacy() && in_array(SelectOption::Distinct, $select->options, true)
            && !in_array(SelectOption::BigResult, $select->options, true)
            && !$this->indexed($plan, $keys, $select->from, $keys === []);
    }

    /**
     * Converts only selected BIT sort columns when they pass through storage.
     *
     * @param list<array{int, Domain, bool}> $keys The projected sort positions
     */
    public function apply(QueryPlan $plan, Select $select, array $keys, ?Scope $outer): QueryPlan
    {
        if (!in_array(SelectOption::Distinct, $select->options, true) || $keys === [] || $this->planner->blocks->empty($select, $outer) || $this->indexed($plan, $keys, $select->from)) {
            return $plan;
        }
        $domains = $plan->domains;
        foreach ($keys as [$position]) {
            $domain = $domains[$position] ?? null;
            if ($domain?->kind === Kind::Bit) {
                $domains[$position] = Domain::of((new Materialization())->ordered($domain->resolved()), $domain->nullable);
            }
        }

        return new QueryPlan($this->planner->settled($plan, $domains), $domains, $plan->names, array_map(static fn (?ColumnOrigin $origin): ?ColumnOrigin => $origin?->unkeyed(), $plan->origins));
    }

    /**
     * Detects a covering index or a selected non-null unique key on one table.
     *
     * @param list<array{int, Domain, bool}> $keys The projected sort positions
     * @param bool $groupOnly Whether only grouping can avoid temporary duplicate storage
     */
    public function indexed(QueryPlan $plan, array $keys, ?Relation $source = null, bool $groupOnly = false): bool
    {
        while ($source instanceof NestedRelation) {
            $source = $source->relation;
        }
        if (!$source instanceof TableReference) {
            return false;
        }
        $first = $plan->origins[0] ?? null;
        $table = $first === null ? null : $this->planner->dictionary->table($first->schema, $first->originalTable);
        if ($table === null) {
            return false;
        }
        $selected = [];
        foreach ($plan->origins as $origin) {
            $position = $origin === null ? null : $table->definition->position($origin->column);
            if ($origin === null || $origin->schema !== $first->schema || $origin->table !== $first->table || $position === null) {
                return false;
            }
            $selected[] = $position;
        }
        foreach ($table->definition->keys as $index) {
            $required = array_diff($index->columns, $selected);
            $nullable = array_filter($index->columns, static fn (int $position): bool => $table->definition->columns[$position]->nullable());
            if ($index->unique() && $required === [] && $nullable === []) {
                return true;
            }
            $skip = $this->skip($table, $index, $source, $groupOnly);
            if ($index->access($table->definition->engine) === IndexAlgorithm::Btree && ($this->available($index, $source, !$groupOnly) || $skip) && $this->ordered($index, $selected, $keys)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detects a sampled index grouping scan that removes duplicates before temporary storage.
     */
    public function skip(\MySqlMemory\Dictionary\StoredTable $table, Key $index, TableReference $source, bool $groupOnly): bool
    {
        return $groupOnly && !$table->definition->temporary && $index->visible && $table->indexStatistics?->groups($index->name) === true
            && $this->allowed($index->name, $source->indexHints, IndexHintScope::Join);
    }

    /**
     * Requires complete indexed values and one consistent scan direction.
     * Prefix indexes cannot supply a selected value beyond their prefix. A
     * mixed ASC/DESC ordering needs matching key directions, or their reversal.
     *
     * @param list<int> $selected The table positions of the selected columns
     * @param list<array{int, Domain, bool}> $keys The projected sort positions
     */
    public function ordered(Key $index, array $selected, array $keys): bool
    {
        $ordered = array_map(static fn (array $key): ?int => $selected[$key[0]] ?? null, $keys);
        if (array_diff($selected, $index->columns) !== [] || $ordered !== array_slice($index->columns, 0, count($ordered))) {
            return false;
        }
        foreach ($index->columns as $position => $column) {
            if (in_array($column, $selected, true) && ($index->prefixes[$position] ?? null) !== null) {
                return false;
            }
        }
        $reverse = null;
        foreach ($keys as $position => [, , $descending]) {
            $direction = !$this->planner->settings->legacy() && ($index->descending[$position] ?? false);
            $opposite = $direction !== $descending;
            if ($reverse !== null && $reverse !== $opposite) {
                return false;
            }
            $reverse = $opposite;
        }

        return true;
    }

    /**
     * Checks visibility and the hint scopes that can avoid duplicate storage.
     *
     * @param bool $ordered Whether the query also orders, allowing an ORDER BY index
     */
    public function available(Key $index, TableReference $source, bool $ordered = true): bool
    {
        $visible = $index->visible || str_contains((string) $this->planner->compiler->connection->variables->read('optimizer_switch'), 'use_invisible_indexes=on');

        return $visible && (($ordered && $this->allowed($index->name, $source->indexHints, IndexHintScope::OrderBy)) || $this->allowed($index->name, $source->indexHints, IndexHintScope::GroupBy));
    }

    /**
     * Applies USE/FORCE unions and IGNORE exclusions in one index-hint scope.
     * A covering index can avoid DISTINCT storage through grouping or ordering;
     * disabling only its JOIN scope does not prevent either use.
     *
     * @param list<IndexHint> $hints The hints on the table reference
     */
    public function allowed(string $name, array $hints, IndexHintScope $scope): bool
    {
        $selected = null;
        $ignored = false;
        foreach ($hints as $hint) {
            if ($hint->scope !== null && $hint->scope !== $scope) {
                continue;
            }
            $names = array_map(static fn ($index): string => $index instanceof Name ? strtolower($index->value) : 'primary', $hint->indexes);
            $named = in_array(strtolower($name), $names, true);
            if ($hint->action === IndexHintAction::Ignore) {
                $ignored = $ignored || $named;
            } else {
                $selected = $selected === true || $named;
            }
        }

        return !$ignored && $selected !== false;
    }
}
