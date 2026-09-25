<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Laravel;

use SqlCatalog\Core\Evaluation\Domain;

/**
 * Projection, ordering, grouping, having and join effects on a query state.
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
            'from' => $this->from($state, $arguments),
            'orderby', 'orderbydesc' => $this->order($state, $arguments, $method === 'orderbydesc'),
            'latest', 'oldest' => count($arguments) <= 1 ? $this->order($state, [$arguments[0] ?? Domain::literal('created_at')], $method === 'latest') : $state->reject('Laravel ' . $method . ' overload is not modelled'),
            'orderbyraw' => $this->raw($state, $arguments, 'orders', 'orderBindings'),
            'groupby' => $this->group($state, $arguments),
            'having', 'orhaving' => $this->having($state, $arguments, $method === 'orhaving' ? 'or' : 'and'),
            'havingraw', 'orhavingraw' => $this->raw($state, $arguments, 'having', 'havingBindings', $method === 'orhavingraw' ? 'or' : 'and'),
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
     * @return list<Domain>|null Columns from an array or variadic arguments; an unknown list stays one open column.
     */
    public function columns(array $arguments): ?array
    {
        if (count($arguments) === 1 && $arguments[0]->soleArray() !== null) {
            $array = $arguments[0]->soleArray();
            if ($array->named() !== []) {
                return null;
            }

            return $array->complete ? $array->positional() : [$arguments[0]];
        }

        return $arguments;
    }

    /**
     * @param list<Domain> $arguments Replaces the table, with an optional alias.
     */
    public function from(QueryState $state, array $arguments): QueryState
    {
        if (!in_array(count($arguments), [1, 2], true) || $arguments[0]->soleObject() !== null) {
            return $state->reject('Laravel from overload is not modelled');
        }
        $table = $arguments[0];
        $alias = $arguments[1] ?? Domain::literal(null);
        if ($alias->soleLiteral() === null || $alias->soleLiteral()->value !== null) {
            $table = $table->concat(Domain::literal(' as '))->concat($alias);
        }

        return $state->with('table', $table);
    }

    /**
     * @param list<Domain> $arguments Appends raw SQL and its bindings to their respective components.
     */
    public function raw(QueryState $state, array $arguments, string $field, string $bindings, ?string $boolean = null): QueryState
    {
        $values = in_array(count($arguments), [1, 2], true) ? $this->grammar->rawBindings($arguments[0], $arguments[1] ?? QueryState::list([])) : null;
        if ($values === null) {
            return $state->reject('Laravel raw clause bindings are incomplete');
        }
        $prefix = $boolean === null || $state->items($field) === [] ? '' : $boolean . ' ';

        return $state->append($field, [Domain::literal($prefix)->concat($arguments[0])])->append($bindings, $values);
    }

    /**
     * @param list<Domain> $arguments Appends an ordering after validating the direction.
     */
    public function order(QueryState $state, array $arguments, bool $descending): QueryState
    {
        $direction = $descending ? Domain::literal('desc') : ($arguments[1] ?? Domain::literal('asc'));
        $literal = $direction->soleLiteral()?->value;
        if (!in_array(count($arguments), [1, 2], true) || $direction->soleArray() !== null || $direction->soleObject() !== null) {
            return $state->reject('Laravel ordering is unresolved');
        }
        if ($direction->soleLiteral() !== null) {
            if (!is_string($literal) || !in_array(strtolower($literal), ['asc', 'desc'], true)) {
                return $state->reject('Laravel ordering direction is invalid');
            }
            $direction = Domain::literal(strtolower($literal));
        }

        return $state->append('orders', [$this->grammar->wrap($arguments[0])->concat(Domain::literal(' '))->concat($direction)]);
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
     * @param list<Domain> $arguments A having comparison, compiled like a basic where.
     */
    public function having(QueryState $state, array $arguments, string $boolean): QueryState
    {
        $comparison = (new Predicates($this->grammar))->comparison($arguments);
        if ($comparison === null) {
            return $state->reject('Laravel having overload is not modelled');
        }
        $prefix = $state->items('having') === [] ? '' : $boolean . ' ';

        return $state->append('having', [Domain::literal($prefix)->concat($comparison[0])])->append('havingBindings', $comparison[1]);
    }

    /**
     * @param list<Domain> $arguments Sets a limit or offset the way Laravel normalizes them, leaving an unresolved number open.
     *
     * A null or negative limit leaves the window unlimited or unchanged; an offset is clamped to zero.
     */
    public function number(QueryState $state, array $arguments, string $field): QueryState
    {
        $value = $arguments[0] ?? Domain::unknown();
        $literal = $value->soleLiteral();
        if (count($arguments) !== 1 || $value->soleArray() !== null || $value->soleObject() !== null) {
            return $state->reject('Laravel ' . $field . ' is not a number');
        }
        if ($literal === null) {
            return $state->with($field, $value);
        }
        if ($literal->value === null) {
            return $state->with($field, $field === 'offset' ? Domain::literal(0) : Domain::literal(null));
        }
        if (!is_int($literal->value) && !is_numeric($literal->value)) {
            return $state->reject('Laravel ' . $field . ' is not a number');
        }
        if ($field === 'offset') {
            return $state->with($field, Domain::literal(max(0, (int) $literal->value)));
        }

        return (int) $literal->value >= 0 ? $state->with($field, Domain::literal((int) $literal->value)) : $state;
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
