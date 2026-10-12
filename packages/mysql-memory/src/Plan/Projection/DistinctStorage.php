<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Projection;

use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\Assignment;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\ColumnOrigin;
use MySqlMemory\Plan\ConstantTables;
use MySqlMemory\Plan\Grouping;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Transform\Buffer;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Path\Transform\Sort;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Plan\Window\Windowing;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Shape\Field;

/**
 * Resolves DISTINCT fields outside windows and publishes their copied assignments.
 *
 * An unsorted bounded result can emit duplicate-free rows as they arrive.
 * Full results, sorting and SQL_CALC_FOUND_ROWS read stored assignment fields
 * after input effects finish. Constant expressions retain expression metadata.
 * Legacy bounded unsorted queries probe one input row when their expressions do
 * not depend on table columns. Verified through SQL and PDO on MySQL 5.6, 5.7, 8.0, 8.4 and 9.1.
 *
 * @visibility MySqlMemory
 */
final class DistinctStorage
{
    /**
     * @param Planner $planner The statement planner
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Resolves stored metadata and restores direct assignments as rows are emitted.
     *
     * @param list<Evaluable> $expressions The select list and hidden sorting keys
     */
    public function apply(QueryPlan $plan, Select $select, ?Scope $outer, array $expressions, bool $sorted): QueryPlan
    {
        if (!$this->active($select, $outer)) {
            return $plan;
        }
        $buffered = $sorted || ($select->limit === null && $select->late?->limit === null) || in_array(SelectOption::CalcFoundRows, $select->options, true);
        $domains = [];
        $origins = [];
        $outputs = [];
        $copied = [];
        foreach ($this->planner->blocks->fields($select) as $position => $field) {
            $varying = $field->expression !== null && !$this->planner->compiler->constancy($field->expression, assignmentsVary: false)->constant();
            $domain = $plan->domains[$position];
            $origin = $plan->origins[$position] ?? null;
            if ($varying && $domain->kind === Kind::String) {
                $domain = Domain::of((new Materialization())->column($domain->resolved(), release: $this->planner->settings->release()), $domain->nullable);
            }
            $read = new ColumnRead($domain, $position);
            $value = $expressions[$position];
            while ($value instanceof Retyped) {
                $value = $value->evaluable;
            }
            if ($buffered && $varying && $value instanceof Assignment) {
                $read = new Assignment($value->name, $read, $this->planner->compiler->names->stored($domain), $domain);
                $origin = $this->planner->settings->legacy() ? $origin : new ColumnOrigin('', '<temporary>', '', '', $this->planner->materialized([$domain])[0]->flags ?? 0);
                $copied[$position] = true;
            }
            $outputs[] = $read;
            $domains[] = $domain;
            $origins[] = $origin;
        }
        $root = (new Emission($this->planner))->bounded($this->constants($plan->root, $buffered), count($domains), false);
        $root = $copied !== [] ? new Project($this->buffer($root, $expressions, $copied), $outputs) : $root;

        return new QueryPlan($root, $domains, $plan->names, $origins);
    }

    /**
     * Keeps empty, direct aggregate and window queries on their own field paths.
     * Legacy grouping retains its separate field behavior.
     */
    public function active(Select $select, ?Scope $outer): bool
    {
        return in_array(SelectOption::Distinct, $select->options, true)
            && ($this->planner->settings->legacy() ? $select->groupBy === null && (new Grouping($this->planner))->collect($select) === [] : !(new \SqlSemantics\Platform\MySql\Rules\Query\Grouping\GroupKeys())->direct($select, $this->planner->compiler->facts))
            && (new Windowing($this->planner))->calls($select) === []
            && !$this->planner->blocks->empty($select, $outer)
            && (new ConstantTables($this->planner->compiler->facts))->joined($select) > 0;
    }

    /**
     * Bounds legacy DISTINCT input when every output is independent of table rows.
     * An unsorted limited result probes one row even when its variable assignment
     * would change on another read. Sorting and calculated counts retain all input.
     * Verified through SQL on MySQL 5.6 and 5.7 in Testcontainers.
     *
     * @param list<Field> $fields The selected fields
     */
    public function input(Select $select, AccessPath $input, array $fields, bool $sorted): AccessPath
    {
        if (!$this->planner->settings->legacy() || $sorted || ($select->limit === null && $select->late?->limit === null)
            || in_array(SelectOption::CalcFoundRows, $select->options, true) || $select->groupBy !== null || (new Grouping($this->planner))->collect($select) !== []) {
            return $input;
        }
        foreach ($fields as $field) {
            if ($field->expression === null || !Constancy::of($field->expression, $this->planner->compiler->facts, assignmentsVary: false, legacy: true)->constant()) {
                return $input;
            }
        }

        return new Limit($input, 1);
    }

    /**
     * Completes stored input effects below every output bound before copied reads.
     * Sorting aliases also publish their stored assignment before any row is skipped.
     *
     * @param list<Evaluable> $expressions The select list and hidden keys
     * @param array<int, true> $positions The stored assignment positions
     */
    public function buffer(AccessPath $path, array $expressions, array $positions): AccessPath
    {
        if ($path instanceof Project) {
            return new Project($this->buffer($path->input, $expressions, $positions), $path->expressions, $path->append, $path->retainAt, $path->publish, $path->order, $path->initial);
        }
        if ($path instanceof Limit) {
            return new Limit($this->buffer($path->input, $expressions, $positions), $path->count, $path->offset, $path->countFound, $path->exhaust, $path->initialize);
        }
        if ($path instanceof Sort) {
            $keys = array_intersect_key(array_fill_keys(array_column($path->keys, 0), true), $positions);
            $input = (new \MySqlMemory\Plan\Window\CopiedAssignments($this->planner))->sorted(new Buffer($path->input), $expressions, $keys);

            return new Sort($input, $path->keys);
        }

        return new Buffer($path);
    }

    /**
     * Holds DISTINCT input values before deferred constant assignments are emitted.
     */
    public function constants(AccessPath $path, bool $buffered): AccessPath
    {
        if ($path instanceof Limit) {
            return new Limit($this->constants($path->input, $buffered), $path->count, $path->offset, $path->countFound, $path->exhaust, $path->initialize);
        }
        if ($path instanceof Sort) {
            return new Sort($this->constants($path->input, $buffered), $path->keys);
        }
        if ($buffered && $path instanceof Project) {
            return new Project(new Buffer($path->input), $path->expressions, $path->append, $path->retainAt, $path->publish, $path->order, $path->initial);
        }

        return $path;
    }
    /**
     * Selects legacy numeric expression keys that compare NULL with their stored zero.
     *
     * MySQL 5.6 and 5.7 grouped DISTINCT compares computed numeric fields this way,
     * while direct columns, strings and ROLLUP retain their separate NULL keys.
     * The first row keeps its original value. Verified through SQL in Testcontainers.
     *
     * @param list<Field> $fields The selected fields
     * @param list<Domain> $domains Their result domains
     * @return array<int, true>
     */
    public function numericNulls(Select $select, array $fields, array $domains): array
    {
        if (!$this->planner->settings->legacy() || $select->groupBy === null || $select->groupBy->modifier !== null) {
            return [];
        }
        $positions = [];
        foreach ($fields as $position => $field) {
            $expression = $field->expression === null ? null : (new \SqlSemantics\Platform\MySql\Rules\Query\Grouping\Matching())->unwrap($field->expression);
            if ($expression !== null && !$expression instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse && in_array($domains[$position]->kind, [Kind::Integer, Kind::Decimal, Kind::Double], true)) {
                $positions[$position] = true;
            }
        }
        return $positions;
    }

    /**
     * Orders stored grouped duplicate keys before an independent output sort.
     *
     * Modern numeric DISTINCT results use ordered duplicate storage after grouping,
     * except when the group keys already make them unique. Ordered GROUP_CONCAT
     * results compare their stored strings too; computed string parents, rollup
     * rows and windows keep their own storage order. Verified through SQL on MySQL 8.4.
     *
     * @param list<Domain> $domains The selected result domains
     * @param bool $sorted Whether an independent output sort follows
     */
    public function grouped(AccessPath $path, Select $select, array $domains, bool $sorted = false): AccessPath
    {
        if ($this->planner->settings->legacy() || !in_array(SelectOption::Distinct, $select->options, true)
            || $select->groupBy === null || $select->groupBy->modifier !== null
            || (new Windowing($this->planner))->calls($select) !== []) {
            return $path;
        }
        $expressions = [];
        foreach ($select->items as $item) {
            if (!$item instanceof \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression) {
                return $path;
            }
            $expressions[] = $item->expression;
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\Query\Grouping\DistinctOrdering())->redundant($select, $expressions, $this->planner->compiler->facts)) {
            return $path;
        }
        $keys = [];
        foreach ($domains as $position => $domain) {
            $target = (new \SqlSemantics\Platform\MySql\Rules\Query\Grouping\Matching())->unwrap($expressions[$position]);
            $collected = $sorted && $target instanceof \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
            if (!$collected && !in_array($domain->kind, [Kind::Integer, Kind::Decimal, Kind::Double, Kind::Null], true)) {
                return $path;
            }
            $keys[] = [$position, $domain, false];
        }

        return new Sort($path, $keys);
    }

}
