<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Laravel;

use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Type\TypeShape;

/**
 * Compiles a read after all query mutations have run.
 *
 * @visibility root
 */
final class SelectCompiler
{
    /**
     * The execution calls this compiler reads as one SELECT.
     */
    public const READS = ['get', 'all', 'first', 'firstorfail', 'find', 'findorfail', 'sole', 'value', 'pluck', 'count', 'sum', 'avg', 'min', 'max', 'exists', 'doesntexist', 'cursor', 'lazy', 'chunk', 'each', 'paginate', 'simplepaginate'];

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
        if (in_array($method, ['first', 'firstorfail', 'value'], true)) {
            $state = $state->with('limit', Domain::literal(1));
        }
        if ($method === 'sole') {
            $state = $state->with('limit', Domain::literal(2));
        }
        if (in_array($method, ['find', 'findorfail'], true)) {
            $state = $this->key($state, $arguments[0] ?? Domain::unknown());
            $arguments = array_slice($arguments, 1);
        }
        if (in_array($method, ['chunk', 'each', 'paginate', 'simplepaginate'], true)) {
            $state = $this->page($state, $method, $arguments);
            $arguments = in_array($method, ['paginate', 'simplepaginate'], true) ? array_slice($arguments, 1, 1) : [];
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
     * The primary key predicate of a find: one row for a scalar, a list for an array.
     */
    public function key(QueryState $state, Domain $id): QueryState
    {
        $predicates = new Predicates($this->grammar);
        if ($id->soleArray() !== null || $id->type()->names === ['array']) {
            return $predicates->in($state, [$state->get('key'), $id], 'and', false);
        }

        return $predicates->basic($state, [$state->get('key'), $id], 'and')->with('limit', Domain::literal(1));
    }

    /**
     * @param list<Domain> $arguments The page window of a chunked or paginated read; the page itself comes from the request or the loop.
     */
    public function page(QueryState $state, string $method, array $arguments): QueryState
    {
        $size = $arguments[0] ?? Domain::literal(15);
        $literal = $size->soleLiteral();
        if ($literal !== null && is_int($literal->value) && $method === 'simplepaginate') {
            $size = Domain::literal($literal->value + 1);
        }
        $state = (new Clauses($this->grammar))->number($state, [$size], 'limit');
        $origin = in_array($method, ['chunk', 'each'], true) ? Origin::Loop : Origin::External;

        return $state->with('offset', Domain::opaque(TypeShape::of(['int']), $origin, in_array($method, ['chunk', 'each'], true) ? 'Laravel chunk offset' : 'Laravel page offset'));
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
                $sql = $sql->concat(Domain::literal($prefix))->concat($this->grammar->join($state->items($field), in_array($field, ['joins', 'where', 'having'], true) ? ' ' : ', '));
            }
        }
        foreach (['limit', 'offset'] as $field) {
            $value = $state->get($field);
            $literal = $value->soleLiteral();
            if ($literal === null || $literal->value !== null) {
                $sql = $sql->concat(Domain::literal(' ' . $field . ' '))->concat($literal === null ? $value : Domain::literal((string) $literal->value));
            }
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

    /**
     * @return array{Domain, Domain} The row count Laravel reads before a page, without the page's ordering and window.
     */
    public function total(QueryState $state): array
    {
        $state = $state->with('limit', Domain::literal(null))->with('offset', Domain::literal(null))->with('columns', QueryState::list([]));
        if ($state->items('groups') !== [] || $state->items('having') !== [] || $state->get('distinct')->soleLiteral()?->value === true) {
            $state = $state->reject('Laravel pagination over grouped or distinct queries is not modelled');
        }

        return $this->aggregate($state, 'count', []);
    }
}
