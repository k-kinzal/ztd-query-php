<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Laravel;

use SqlCatalog\Evaluation\Domain;

/**
 * Projection, ordering, grouping and join effects on a query state.
 *
 * @visibility root
 */
final class Clauses
{
    /**
     * Uses the configured framework SQL grammar.
     */
    public function __construct(private readonly Grammar $grammar)
    {
    }

    /**
     * @param list<Domain> $arguments A recognized mutation, or null.
     */
    public function apply(QueryState $state, string $method, array $arguments): ?QueryState
    {
        return match ($method) {
            'select', 'addselect' => $this->select($state, $arguments, $method === 'addselect'),
            'selectraw' => $this->raw($state, $arguments, 'columns', 'selectBindings'),
            'orderby', 'orderbydesc' => $this->order($state, $arguments, $method === 'orderbydesc'),
            'orderbyraw' => $this->raw($state, $arguments, 'orders', 'orderBindings'),
            'groupby' => $this->group($state, $arguments),
            'havingraw' => $this->raw($state, $arguments, 'having', 'havingBindings'),
            'limit', 'take' => $this->number($state, $arguments, 'limit'),
            'offset', 'skip' => $this->number($state, $arguments, 'offset'),
            'distinct' => $arguments === [] ? $state->with('distinct', Domain::literal(true)) : $state->reject('Laravel distinct arguments are not modelled'),
            'join', 'leftjoin', 'rightjoin' => $this->join($state, $arguments, $method),
            default => null,
        };
    }

    /**
     * @param list<Domain> $arguments Sets or appends explicitly selected columns.
     */
    public function select(QueryState $state, array $arguments, bool $append = false): QueryState
    {
        $columns = $this->columns($arguments);
        if ($columns === null || $columns === []) {
            return $state->reject('Laravel selected columns are incomplete');
        }
        if (!$append) {
            $state = $state->with('columns', QueryState::list([]))->with('selectBindings', QueryState::list([]));
        }

        $wrapped = array_map($this->grammar->wrap(...), $columns);
        if ($append) {
            $existing = $state->items('columns');
            foreach ($wrapped as $column) {
                if (!in_array($column->signature(), array_map(static fn (Domain $value): string => $value->signature(), $existing), true)) {
                    $existing[] = $column;
                }
            }

            return $state->with('columns', QueryState::list($existing));
        }

        return $state->append('columns', $wrapped);
    }

    /**
     * @param list<Domain> $arguments
     * @return list<Domain>|null Columns from an array or variadic arguments.
     */
    public function columns(array $arguments): ?array
    {
        if (count($arguments) === 1 && $arguments[0]->soleArray() !== null) {
            $array = $arguments[0]->soleArray();

            return $array->complete && $array->named() === [] ? $array->positional() : null;
        }

        return $arguments;
    }

    /**
     * @param list<Domain> $arguments Appends raw SQL and its bindings to their respective components.
     */
    public function raw(QueryState $state, array $arguments, string $field, string $bindings): QueryState
    {
        $values = isset($arguments[1]) ? $arguments[1]->soleArray() : QueryState::list([])->soleArray();
        if (!in_array(count($arguments), [1, 2], true) || $values === null || !$values->complete) {
            return $state->reject('Laravel raw clause bindings are incomplete');
        }

        return $state->append($field, [$arguments[0]])->append($bindings, array_map(static fn (\SqlCatalog\Evaluation\ArrayEntry $entry): Domain => $entry->value, $values->entries));
    }

    /**
     * @param list<Domain> $arguments Appends an ordering after validating the direction.
     */
    public function order(QueryState $state, array $arguments, bool $descending): QueryState
    {
        $direction = $descending ? 'desc' : ($arguments[1] ?? Domain::literal('asc'))->soleLiteral()?->value;
        if (!in_array(count($arguments), [1, 2], true) || !is_string($direction) || !in_array(strtolower($direction), ['asc', 'desc'], true)) {
            return $state->reject('Laravel ordering is unresolved');
        }

        return $state->append('orders', [$this->grammar->wrap($arguments[0])->concat(Domain::literal(' ' . strtolower($direction)))]);
    }

    /**
     * @param list<Domain> $arguments Appends grouping identifiers.
     */
    public function group(QueryState $state, array $arguments): QueryState
    {
        $columns = $this->columns($arguments);

        return $columns === null ? $state->reject('Laravel grouping is incomplete') : $state->append('groups', array_map($this->grammar->wrap(...), $columns));
    }

    /**
     * @param list<Domain> $arguments Sets a known non-negative limit or offset.
     */
    public function number(QueryState $state, array $arguments, string $field): QueryState
    {
        $value = ($arguments[0] ?? Domain::unknown())->soleLiteral()?->value;

        return count($arguments) === 1 && is_int($value) && $value >= 0
            ? $state->with($field, Domain::literal($value))
            : $state->reject('Laravel ' . $field . ' is unresolved');
    }

    /**
     * @param list<Domain> $arguments A simple join between two columns.
     */
    public function join(QueryState $state, array $arguments, string $method): QueryState
    {
        if (count($arguments) !== 4) {
            return $state->reject('Laravel join overload is not modelled');
        }
        $operator = $arguments[2]->soleLiteral()?->value;
        if (!is_string($operator) || !in_array($operator, ['=', '<', '>', '<=', '>=', '<>', '!='], true)) {
            return $state->reject('Laravel join operator is unresolved');
        }
        $type = match ($method) {
            'leftjoin' => 'left', 'rightjoin' => 'right', default => 'inner'
        };
        $sql = Domain::literal($type . ' join ')->concat($this->grammar->wrap($arguments[0]))->concat(Domain::literal(' on '))
            ->concat($this->grammar->wrap($arguments[1]))->concat(Domain::literal(' ' . $operator . ' '))->concat($this->grammar->wrap($arguments[3]));

        return $state->append('joins', [$sql]);
    }
}
