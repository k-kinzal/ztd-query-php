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
        if ($method === 'delete' && $arguments === []) {
            return [Domain::literal('delete from ')->concat($this->grammar->wrap($state->get('table')))->concat($this->where($state)), QueryState::list($state->items('whereBindings'))];
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
