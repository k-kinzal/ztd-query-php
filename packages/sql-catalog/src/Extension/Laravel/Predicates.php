<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Laravel;

use SqlCatalog\Core\Evaluation\ArrayEntry;
use SqlCatalog\Core\Evaluation\ArrayTerm;
use SqlCatalog\Core\Evaluation\Domain;

/**
 * Predicate effects, retaining Laravel's clause and binding order.
 *
 * A comparison value that is not a literal null is compiled as a bound
 * placeholder, and a list whose contents are unknown as a placeholder list of
 * unknown length. Laravel's null normalization and empty-set constants are
 * reconstructed from literals only, since the null and emptiness guards that
 * usually precede such calls are not evaluated.
 *
 * @visibility root
 */
final class Predicates
{
    private const OPERATORS = ['=', '<', '>', '<=', '>=', '<>', '!=', 'like', 'not like'];

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
        if (count($arguments) === 1 && $arguments[0]->soleArray() !== null) {
            return $this->group($state, $arguments[0]->soleArray(), $boolean);
        }
        if (count($arguments) === 4) {
            $boolean = $arguments[3]->soleLiteral()?->value;
            if (!is_string($boolean) || !in_array(strtolower($boolean), ['and', 'or'], true)) {
                return $state->reject('Laravel predicate boolean is unresolved');
            }
            $boolean = strtolower($boolean);
            $arguments = array_slice($arguments, 0, 3);
        }
        if (!in_array(count($arguments), [2, 3], true)) {
            return $state->reject('Laravel where overload is not modelled');
        }
        [$column, $operator, $value] = count($arguments) === 2 ? [$arguments[0], Domain::literal('='), $arguments[1]] : $arguments;
        $op = $operator->soleLiteral()?->value;
        $literal = $value->soleLiteral();
        if (is_string($op) && $literal !== null && $literal->value === null) {
            return in_array($op, ['=', '<>', '!='], true)
                ? $this->nulls($state, [$column], $boolean, $op !== '=')
                : $state->reject('Invalid Laravel null comparison');
        }
        $comparison = $this->comparison($arguments);
        if ($comparison === null) {
            return $state->reject('Laravel comparison operator or value is not modelled');
        }

        return $this->add($state, $comparison[0], $comparison[1], $boolean);
    }

    /**
     * @param list<Domain> $arguments
     * @return array{Domain, list<Domain>}|null A column, operator and bound value, or null for an unmodelled form.
     */
    public function comparison(array $arguments): ?array
    {
        if (!in_array(count($arguments), [2, 3], true)) {
            return null;
        }
        [$column, $operator, $value] = count($arguments) === 2 ? [$arguments[0], Domain::literal('='), $arguments[1]] : $arguments;
        $op = $operator->soleLiteral()?->value;
        if (!is_string($op) || !in_array(strtolower($op), self::OPERATORS, true) || $value->soleArray() !== null) {
            return null;
        }
        $sql = $this->grammar->wrap($column)->concat(Domain::literal(' ' . $op . ' '))->concat($this->grammar->parameter($value));

        return [$sql, $this->grammar->bindings([$value])];
    }

    /**
     * An array of comparisons, nested in parentheses the way Laravel adds them.
     */
    public function group(QueryState $state, ArrayTerm $entries, string $boolean): QueryState
    {
        if (!$entries->complete) {
            return $state->reject('Laravel where array is incomplete');
        }
        $inner = new QueryState();
        foreach ($entries->entries as $entry) {
            $inner = $this->entry($inner, $entry, $boolean);
        }
        if (isset($inner->fields['problem'])) {
            return $state->reject('Laravel where array entry is not modelled');
        }
        if ($inner->items('where') === []) {
            return $state;
        }
        $sql = Domain::literal('(')->concat($this->grammar->join($inner->items('where'), ' '))->concat(Domain::literal(')'));

        return $this->add($state, $sql, $inner->items('whereBindings'), $boolean);
    }

    /**
     * One entry of a where array: a column keyed value, or a positional argument list.
     */
    public function entry(QueryState $inner, ArrayEntry $entry, string $boolean): QueryState
    {
        $key = $entry->scalarKey();
        if (is_string($key)) {
            return $this->basic($inner, [Domain::literal($key), Domain::literal('='), $entry->value], $boolean);
        }
        $row = $entry->value->soleArray();
        if ($row === null || !$row->complete || $row->named() !== [] || count($row->entries) > 3 || ($entry->key !== null && $key === null)) {
            return $inner->reject('Laravel where array entry is not modelled');
        }

        return $this->basic($inner, array_merge($row->positional(), [Domain::literal($boolean)]), $boolean);
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
        $columns = $array === null ? [$arguments[0]] : array_map(static fn (ArrayEntry $entry): Domain => $entry->value, $array->entries);
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
        if (count($arguments) !== 2) {
            return $state->reject('Laravel IN overload is not modelled');
        }
        $values = $arguments[1]->soleArray();
        if ($values === null || !$values->complete) {
            $sql = $this->grammar->wrap($arguments[0])->concat(Domain::literal($not ? ' not in (' : ' in ('))->concat($this->grammar->placeholders($arguments[1]))->concat(Domain::literal(')'));

            return $this->add($state, $sql, [], $boolean);
        }
        $items = array_map(static fn (ArrayEntry $entry): Domain => $entry->value, $values->entries);
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
        if (count($arguments) !== 2 || $arguments[1]->soleObject() !== null) {
            return $state->reject('Laravel BETWEEN overload is not modelled');
        }
        $values = $arguments[1]->soleArray();
        $items = $values === null || !$values->complete
            ? [$this->grammar->element($arguments[1]), $this->grammar->element($arguments[1])]
            : array_slice(array_map(static fn (ArrayEntry $entry): Domain => $entry->value, $values->entries), 0, 2);
        if (count($items) !== 2) {
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
     * @param list<Domain> $arguments Explicit SQL with its bindings, one per placeholder when the array is unknown.
     */
    public function raw(QueryState $state, array $arguments, string $boolean): QueryState
    {
        $bindings = in_array(count($arguments), [1, 2], true) ? $this->grammar->rawBindings($arguments[0], $arguments[1] ?? QueryState::list([])) : null;
        if ($bindings === null) {
            return $state->reject('Laravel raw predicate bindings are incomplete');
        }

        return $this->add($state, $arguments[0], $bindings, $boolean);
    }
}
