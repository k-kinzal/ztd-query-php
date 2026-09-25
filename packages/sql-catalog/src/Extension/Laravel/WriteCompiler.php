<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Laravel;

use SqlCatalog\Core\Evaluation\ArrayTerm;
use SqlCatalog\Core\Evaluation\Domain;

/**
 * Compiles literal-shaped writes while preserving their value domains.
 *
 * @visibility root
 */
final class WriteCompiler
{
    /**
     * Uses the configured framework SQL grammar.
     */
    public function __construct(private readonly Grammar $grammar)
    {
    }

    /**
     * @param list<Domain> $arguments
     * @return array{Domain, Domain} SQL and ordered bindings.
     */
    public function compile(QueryState $state, string $method, array $arguments): array
    {
        if ($state->items('joins') !== [] || $state->items('orders') !== [] || $state->get('limit')->soleLiteral()?->value !== null) {
            return $this->unknown('Laravel write modifiers are not modelled');
        }
        if ($method === 'delete' && count($arguments) <= 1) {
            $state = $arguments === [] ? $state : (new Predicates($this->grammar))->basic($state, [$state->get('table')->concat(Domain::literal('.id')), $arguments[0]], 'and');

            return isset($state->fields['problem']) ? [$state->get('problem'), Domain::unknown()]
                : [Domain::literal('delete from ')->concat($this->grammar->wrap($state->get('table')))->concat($this->where($state)), QueryState::list($state->items('whereBindings'))];
        }
        if (in_array($method, ['increment', 'decrement'], true)) {
            return $this->step($state, $method, $arguments);
        }
        $values = ($arguments[0] ?? Domain::unknown())->soleArray();
        if ($values === null || !$values->complete || $values->entries === []) {
            return $this->unknown('Laravel write columns are incomplete');
        }
        if ($method === 'update' && count($arguments) === 1) {
            return $this->update($state, $values);
        }
        if (in_array($method, ['insert', 'insertorignore'], true) && count($arguments) === 1) {
            return $this->insert($state, $values, $method === 'insertorignore');
        }
        if ($method === 'insertgetid' && count($arguments) <= 2) {
            return $this->insertGetId($state, $values, $arguments[1] ?? Domain::literal(null));
        }

        return $this->unknown('Laravel write operation is not modelled: ' . $method);
    }

    /**
     * @return array{Domain, Domain} Update assignments followed by WHERE bindings.
     */
    public function update(QueryState $state, ArrayTerm $values): array
    {
        if ($values->named() === [] || count($values->named()) !== count($values->entries)) {
            return $this->unknown('Laravel update columns are unresolved');
        }
        $parts = [];
        foreach ($values->named() as $column => $value) {
            if ($value->soleArray() !== null) {
                return $this->unknown('Laravel JSON update values are not modelled');
            }
            $parts[] = $this->grammar->wrap(Domain::literal($column))->concat(Domain::literal(' = '))->concat($this->grammar->parameter($value));
        }
        $sql = Domain::literal('update ')->concat($this->grammar->wrap($state->get('table')))
            ->concat(Domain::literal(' set '))->concat($this->grammar->join($parts))->concat($this->where($state));

        return [$sql, QueryState::list(array_merge($this->grammar->bindings(array_values($values->named())), $state->items('whereBindings')))];
    }

    /**
     * @param list<Domain> $arguments
     * @return array{Domain, Domain} An increment or decrement written as Laravel's raw arithmetic update.
     */
    public function step(QueryState $state, string $method, array $arguments): array
    {
        $column = ($arguments[0] ?? Domain::unknown())->soleLiteral()?->value;
        $amount = $arguments[1] ?? Domain::literal(1);
        $extra = isset($arguments[2]) ? $arguments[2]->soleArray() : QueryState::list([])->soleArray();
        if (!is_string($column) || count($arguments) > 3 || $extra === null || !$extra->complete || $amount->soleArray() !== null || $amount->soleObject() !== null) {
            return $this->unknown('Laravel ' . $method . ' overload is not modelled');
        }
        $literal = $amount->soleLiteral()?->value;
        $step = $literal === null ? $amount : Domain::literal(is_numeric($literal) ? (string) $literal : '0');
        $expression = $this->grammar->wrapName($column)->concat(Domain::literal($method === 'increment' ? ' + ' : ' - '))->concat($step);
        $raw = new \SqlCatalog\Core\Evaluation\ObjectTerm(Grammar::EXPRESSION, state: (new QueryState(['sql' => $expression]))->array());
        $values = new ArrayTerm(array_merge([new \SqlCatalog\Core\Evaluation\ArrayEntry(Domain::literal($column), Domain::of($raw))], $extra->entries));

        return $this->update($state, $values);
    }

    /**
     * @return array{Domain, Domain} Inserts one or more complete rows.
     */
    public function insert(QueryState $state, ArrayTerm $values, bool $ignore): array
    {
        $rows = $this->rows($values);
        if ($rows === []) {
            return $this->unknown('Laravel insert rows are inconsistent');
        }
        $columns = array_keys($rows[0]);
        $groups = [];
        $bindings = [];
        foreach ($rows as $row) {
            if (array_keys($row) !== $columns) {
                return $this->unknown('Laravel insert rows have different columns');
            }
            $items = array_values($row);
            $groups[] = Domain::literal('(')->concat($this->grammar->join(array_map($this->grammar->parameter(...), $items)))->concat(Domain::literal(')'));
            $bindings = array_merge($bindings, $this->grammar->bindings($items));
        }
        $prefix = $this->grammar->dialect?->insertPrefix($ignore) ?? 'insert into ';
        $sql = Domain::literal($prefix)->concat($this->grammar->wrap($state->get('table')))->concat(Domain::literal(' ('))
            ->concat($this->grammar->join(array_map(fn (string $column): Domain => $this->grammar->wrap(Domain::literal($column)), $columns)))
            ->concat(Domain::literal(') values '))->concat($this->grammar->join($groups));
        $sql = $sql->concat(Domain::literal($this->grammar->dialect?->insertSuffix($ignore) ?? ''));

        return [$sql, QueryState::list($bindings)];
    }

    /**
     * @return array{Domain, Domain} A single-row insert whose generated key the grammar may have to return.
     */
    public function insertGetId(QueryState $state, ArrayTerm $values, Domain $sequence): array
    {
        if ($values->named() === [] || count($values->named()) !== count($values->entries)) {
            return $this->unknown('Laravel insertGetId row is unresolved');
        }
        [$sql, $bindings] = $this->insert($state, $values, false);
        $suffix = $this->grammar->dialect?->returningSuffix();
        if ($suffix === null || $suffix === '') {
            return [$sql, $bindings];
        }
        $name = $sequence->soleLiteral();

        return [$sql->concat(Domain::literal($suffix))->concat($name?->value === null ? $this->grammar->wrap(Domain::literal('id')) : $this->grammar->wrap($sequence)), $bindings];
    }

    /**
     * @return list<array<string, Domain>> Single rows retain order; bulk rows sort their keys.
     */
    public function rows(ArrayTerm $values): array
    {
        if ($values->named() !== []) {
            return count($values->named()) === count($values->entries) ? [$values->named()] : [];
        }
        $rows = [];
        foreach ($values->positional() as $value) {
            $row = $value->soleArray();
            if ($row === null || !$row->complete || $row->named() === [] || count($row->named()) !== count($row->entries)) {
                return [];
            }
            $named = $row->named();
            ksort($named);
            $rows[] = $named;
        }

        return $rows;
    }

    /**
     * The shared WHERE clause of an update or delete.
     */
    public function where(QueryState $state): Domain
    {
        return $state->items('where') === [] ? Domain::literal('') : Domain::literal(' where ')->concat($this->grammar->join($state->items('where'), ' '));
    }

    /**
     * @return array{Domain, Domain} A visible gap, never a fabricated exact write.
     */
    public function unknown(string $reason): array
    {
        return [(new QueryState())->reject($reason)->get('problem'), Domain::unknown()];
    }
}
