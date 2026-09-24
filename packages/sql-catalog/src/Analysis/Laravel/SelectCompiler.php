<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Laravel;

use SqlCatalog\Evaluation\Domain;

/**
 * Compiles a read after all query mutations have run.
 *
 * @visibility root
 */
final class SelectCompiler
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
        if (in_array($method, ['first', 'firstorfail'], true)) {
            $state = $state->with('limit', Domain::literal(1));
        }
        if ($method === 'find') {
            if (!isset($arguments[0]) || $arguments[0]->soleArray() !== null) {
                $state = $state->reject('Laravel find overload is not modelled');
            } else {
                $state = (new Predicates($this->grammar))->basic($state, [$state->get('key'), $arguments[0]], 'and')->with('limit', Domain::literal(1));
            }
            $arguments = array_slice($arguments, 1);
        }
        if (in_array($method, ['count', 'sum', 'avg', 'min', 'max'], true)) {
            return $this->aggregate($state, $method, $arguments);
        }
        if ($state->items('columns') === []) {
            $columns = $method === 'pluck' ? $arguments : [$arguments[0] ?? QueryState::list([Domain::literal('*')])];
            $state = (new Clauses($this->grammar))->select($state, $columns);
        }
        if (count($arguments) > ($method === 'pluck' ? 2 : 1)) {
            $state = $state->reject('Laravel read arguments are not modelled');
        }
        [$sql, $bindings] = $this->select($state);
        if ($method === 'exists' || $method === 'doesntexist') {
            $sql = Domain::literal('select exists(')->concat($sql)->concat(Domain::literal(') as '))->concat($this->grammar->wrap(Domain::literal('exists')));
        }

        return [$sql, $bindings];
    }

    /**
     * @return array{Domain, Domain} A SELECT with component-ordered bindings.
     */
    public function select(QueryState $state): array
    {
        $sql = Domain::literal($state->get('distinct')->soleLiteral()?->value === true ? 'select distinct ' : 'select ')
            ->concat($this->grammar->join($state->items('columns')))->concat(Domain::literal(' from '))->concat($this->grammar->wrap($state->get('table')));
        foreach (['joins' => ' ', 'where' => ' where ', 'groups' => ' group by ', 'having' => ' having ', 'orders' => ' order by '] as $field => $prefix) {
            if ($state->items($field) !== []) {
                $sql = $sql->concat(Domain::literal($prefix))->concat($this->grammar->join($state->items($field), in_array($field, ['joins', 'where'], true) ? ' ' : ($field === 'having' ? ' and ' : ', ')));
            }
        }
        $limit = $state->get('limit')->soleLiteral()?->value;
        $offset = $state->get('offset')->soleLiteral()?->value;
        if (is_int($limit)) {
            $sql = $sql->concat(Domain::literal(' limit ' . $limit));
        }
        if (is_int($offset)) {
            $sql = $sql->concat(Domain::literal(' offset ' . $offset));
        }
        $bindings = [];
        foreach (['selectBindings', 'joinBindings', 'whereBindings', 'havingBindings', 'orderBindings'] as $field) {
            $bindings = array_merge($bindings, $state->items($field));
        }
        if (isset($state->fields['problem'])) {
            $sql = $sql->concat(Domain::literal(' '))->concat($state->get('problem'));
        }

        return [$sql, QueryState::list($bindings)];
    }

    /**
     * @param list<Domain> $arguments
     * @return array{Domain, Domain} An aggregate with Laravel's projection reset.
     */
    public function aggregate(QueryState $state, string $method, array $arguments): array
    {
        if (count($arguments) > 1 || $state->items('having') !== []) {
            $state = $state->reject('Laravel aggregate overload is not modelled');
        }
        $column = $arguments[0] ?? Domain::literal('*');
        $aggregate = Domain::literal($method . '(')->concat($this->grammar->wrap($column))->concat(Domain::literal(') as '))->concat($this->grammar->wrap(Domain::literal('aggregate')));
        if ($state->get('distinct')->soleLiteral()?->value === true) {
            $state = $state->reject('Distinct Laravel aggregates are not modelled');
        }
        $state = $state->with('columns', QueryState::list([$aggregate]))->with('selectBindings', QueryState::list([]));
        if ($state->items('groups') === []) {
            $state = $state->with('orders', QueryState::list([]))->with('orderBindings', QueryState::list([]));
        }

        return $this->select($state);
    }
}
