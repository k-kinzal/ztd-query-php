<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Laravel;

use SqlCatalog\Core\Evaluation\Domain;

/**
 * Predicate effects, retaining Laravel's clause and binding order.
 *
 * @visibility root
 */
final class Predicates
{
    /**
     * Uses the configured framework SQL grammar.
     */
    public function __construct(private readonly Grammar $grammar)
    {
    }

    /**
     * @param list<Domain> $arguments A recognized predicate, or null for a different operation.
     */
    public function apply(QueryState $state, string $method, array $arguments): ?QueryState
    {
        $boolean = str_starts_with($method, 'orwhere') ? 'or' : 'and';
        $method = $boolean === 'or' ? substr($method, 2) : $method;

        return match ($method) {
            'where' => $this->basic($state, $arguments, $boolean),
            'wherenull', 'wherenotnull' => $this->nulls($state, $arguments, $boolean, $method === 'wherenotnull'),
            'wherein', 'wherenotin' => $this->in($state, $arguments, $boolean, $method === 'wherenotin'),
            'wherebetween', 'wherenotbetween' => $this->between($state, $arguments, $boolean, $method === 'wherenotbetween'),
            'wherecolumn' => $this->column($state, $arguments, $boolean),
            'whereraw' => $this->raw($state, $arguments, $boolean),
            default => null,
        };
    }

    /**
     * @param list<Domain> $bindings Appends one predicate and the values it binds.
     */
    public function add(QueryState $state, Domain $sql, array $bindings, string $boolean = 'and'): QueryState
    {
        $prefix = $state->items('where') === [] ? '' : $boolean . ' ';

        return $state->append('where', [Domain::literal($prefix)->concat($sql)])->append('whereBindings', $bindings);
    }

    /**
     * Groups existing disjunctions before a global scope adds its predicate.
     */
    public function groupDisjunction(QueryState $state): QueryState
    {
        foreach ($state->items('where') as $predicate) {
            $text = $predicate->soleLiteral()?->value;
            if (is_string($text) && str_starts_with($text, 'or ')) {
                $group = Domain::literal('(')->concat($this->grammar->join($state->items('where'), ' '))->concat(Domain::literal(')'));

                return $state->with('where', QueryState::list([$group]));
            }
        }

        return $state;
    }

    /**
     * @param list<Domain> $arguments A scalar comparison, including Laravel's null normalization.
     */
    public function basic(QueryState $state, array $arguments, string $boolean): QueryState
    {
        if (!in_array(count($arguments), [2, 3], true)) {
            return $state->reject('Laravel where overload is not modelled');
        }
        [$column, $operator, $value] = count($arguments) === 2 ? [$arguments[0], Domain::literal('='), $arguments[1]] : $arguments;
        $op = $operator->soleLiteral()?->value;
        if (!is_string($op) || !in_array(strtolower($op), ['=', '<', '>', '<=', '>=', '<>', '!=', 'like', 'not like'], true)) {
            return $state->reject('Laravel comparison operator is unresolved');
        }
        $literal = $value->soleLiteral();
        if ($literal !== null && $literal->value === null) {
            return in_array($op, ['=', '<>', '!='], true)
                ? $this->nulls($state, [$column], $boolean, $op !== '=')
                : $state->reject('Invalid Laravel null comparison');
        }
        if ($value->type()->isUnknown() || $value->type()->isNullable()) {
            return $state->reject('Nullable Laravel predicate values can change the SQL shape');
        }
        if ($value->soleArray() !== null) {
            return $state->reject('Array-valued Laravel comparison is not modelled');
        }
        $sql = $this->grammar->wrap($column)->concat(Domain::literal(' ' . $op . ' '))->concat($this->grammar->parameter($value));

        return $this->add($state, $sql, $this->grammar->bindings([$value]), $boolean);
    }

    /**
     * @param list<Domain> $arguments NULL tests on one or several columns.
     */
    public function nulls(QueryState $state, array $arguments, string $boolean, bool $not): QueryState
    {
        if (count($arguments) !== 1) {
            return $state->reject('Laravel null predicate arguments are not modelled');
        }
        $array = $arguments[0]->soleArray();
        if ($array !== null && !$array->complete) {
            return $state->reject('Laravel null predicate columns are incomplete');
        }
        $columns = $array === null ? [$arguments[0]] : array_map(static fn (\SqlCatalog\Core\Evaluation\ArrayEntry $entry): Domain => $entry->value, $array->entries);
        foreach ($columns as $column) {
            $state = $this->add($state, $this->grammar->wrap($column)->concat(Domain::literal($not ? ' is not null' : ' is null')), [], $boolean);
        }

        return $state;
    }

    /**
     * @param list<Domain> $arguments IN values, including the empty-set constants.
     */
    public function in(QueryState $state, array $arguments, string $boolean, bool $not): QueryState
    {
        $values = ($arguments[1] ?? Domain::unknown())->soleArray();
        if (count($arguments) !== 2 || $values === null || !$values->complete) {
            return $state->reject('Laravel IN values are not a complete array');
        }
        $items = array_map(static fn (\SqlCatalog\Core\Evaluation\ArrayEntry $entry): Domain => $entry->value, $values->entries);
        if (array_filter($items, static fn (Domain $value): bool => $value->soleArray() !== null) !== []) {
            return $state->reject('Nested Laravel IN values are invalid');
        }
        $sql = $items === [] ? Domain::literal($not ? '1 = 1' : '0 = 1')
            : $this->grammar->wrap($arguments[0])->concat(Domain::literal($not ? ' not in (' : ' in ('))
                ->concat($this->grammar->join(array_map($this->grammar->parameter(...), $items)))->concat(Domain::literal(')'));

        return $this->add($state, $sql, $this->grammar->bindings($items), $boolean);
    }

    /**
     * @param list<Domain> $arguments An ordered pair of bounds.
     */
    public function between(QueryState $state, array $arguments, string $boolean, bool $not): QueryState
    {
        $values = ($arguments[1] ?? Domain::unknown())->soleArray();
        $items = $values === null ? [] : array_map(static fn (\SqlCatalog\Core\Evaluation\ArrayEntry $entry): Domain => $entry->value, $values->entries);
        if (count($arguments) !== 2 || $values === null || !$values->complete || count($items) !== 2) {
            return $state->reject('Laravel BETWEEN bounds are incomplete');
        }
        $sql = $this->grammar->wrap($arguments[0])->concat(Domain::literal($not ? ' not between ' : ' between '))
            ->concat($this->grammar->parameter($items[0]))->concat(Domain::literal(' and '))->concat($this->grammar->parameter($items[1]));

        return $this->add($state, $sql, $this->grammar->bindings($items), $boolean);
    }

    /**
     * @param list<Domain> $arguments A comparison between identifiers.
     */
    public function column(QueryState $state, array $arguments, string $boolean): QueryState
    {
        if (!in_array(count($arguments), [2, 3], true)) {
            return $state->reject('Laravel column comparison overload is not modelled');
        }
        [$left, $operator, $right] = count($arguments) === 2 ? [$arguments[0], Domain::literal('='), $arguments[1]] : $arguments;
        $op = $operator->soleLiteral()?->value;
        if (!is_string($op) || !in_array($op, ['=', '<', '>', '<=', '>=', '<>', '!='], true)) {
            return $state->reject('Laravel column comparison operator is unresolved');
        }

        return $this->add($state, $this->grammar->wrap($left)->concat(Domain::literal(' ' . $op . ' '))->concat($this->grammar->wrap($right)), [], $boolean);
    }

    /**
     * @param list<Domain> $arguments Explicit SQL with a complete ordered binding array.
     */
    public function raw(QueryState $state, array $arguments, string $boolean): QueryState
    {
        $values = isset($arguments[1]) ? $arguments[1]->soleArray() : QueryState::list([])->soleArray();
        if (!in_array(count($arguments), [1, 2], true) || $values === null || !$values->complete) {
            return $state->reject('Laravel raw predicate bindings are incomplete');
        }

        return $this->add($state, $arguments[0], array_map(static fn (\SqlCatalog\Core\Evaluation\ArrayEntry $entry): Domain => $entry->value, $values->entries), $boolean);
    }
}
