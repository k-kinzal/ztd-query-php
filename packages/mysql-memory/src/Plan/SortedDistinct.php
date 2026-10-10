<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

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
     * Converts only selected BIT sort columns when they pass through storage.
     *
     * @param list<array{int, Domain, bool}> $keys The projected sort positions
     */
    public function apply(QueryPlan $plan, Select $select, array $keys, ?Scope $outer): QueryPlan
    {
        if (!in_array(SelectOption::Distinct, $select->options, true) || $keys === [] || $this->planner->blocks->empty($select, $outer) || $this->indexed($plan, $keys)) {
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
     */
    public function indexed(QueryPlan $plan, array $keys): bool
    {
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
            $ordered = array_map(static fn (array $key): ?int => $selected[$key[0]] ?? null, $keys);
            if (array_diff($selected, $index->columns) === [] && $ordered === array_slice($index->columns, 0, count($ordered))) {
                return true;
            }
        }

        return false;
    }
}
