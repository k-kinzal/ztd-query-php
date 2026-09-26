<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Laravel;

use PhpParser\Node\Expr;
use SqlCatalog\Core\Analysis\SinkFinder;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\ObjectTerm;
use SqlCatalog\Core\Extension\Model\QueryModelInterface;
use SqlCatalog\Core\Extension\Model\QueryOutput;
use SqlCatalog\Core\Php\ProgramIndex;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Type\TypeShape;

/**
 * Derives receiver state and arguments together, then compiles at the existing sink.
 *
 * @visibility root
 */
final class BuilderQueries implements QueryModelInterface
{
    /**
     * Configures the source metadata and SQL grammar used by this model.
     */
    public function __construct(private readonly ProgramIndex $index, private readonly \SqlCatalog\Core\Sql\Dialects $dialects = new \SqlCatalog\Core\Sql\Dialects())
    {
    }

    /**
     * @return list<Expr> The receiver and arguments must be derived together.
     */
    public function inputs(Expr\CallLike $call): array
    {
        $receiver = match (true) {
            $call instanceof Expr\MethodCall, $call instanceof Expr\NullsafeMethodCall => $call->var,
            $call instanceof Expr\StaticCall => new Expr\StaticCall($call->class, 'query', [], $call->getAttributes()),
            default => null,
        };

        return $receiver === null ? [] : array_merge([$receiver], array_map(static fn (\PhpParser\Node\Arg $argument): Expr => $argument->value, array_values($call->getArgs())));
    }

    /**
     * @param list<Domain> $values Values derived by the core, without losing branch correspondence.
     * @return list<QueryOutput> The SQL compiled by this extension for each receiver alternative.
     */
    public function statements(Expr\CallLike $call, array $values): array
    {
        $method = (new SinkFinder())->nameOf($call) ?? '';
        $target = array_shift($values) ?? Domain::unknown();
        $outputs = [];
        foreach ($target->terms as $term) {
            $state = $term instanceof ObjectTerm ? QueryState::from($term) : (new QueryState())->reject('Laravel builder state is unavailable');
            if (!(new BuilderCalls($this->index))->positional(array_values($call->getArgs()))) {
                $state = $state->reject('Named or unpacked Laravel arguments are not modelled');
            }
            foreach ($this->outputs($state, strtolower($method), $values) as [$sql, $bindings]) {
                $outputs[] = new QueryOutput($sql, $bindings, $target->widened, $target->combined);
            }
        }

        return $outputs;
    }

    /**
     * @param list<Domain> $arguments
     * @return list<array{Domain, Domain}> Every statement one execution issues, each with its own bindings.
     */
    public function outputs(QueryState $state, string $method, array $arguments): array
    {
        $state = $this->prepare($state, $method);
        $grammar = new Grammar($this->dialects->find($state->string('dialect')));
        if (isset($state->fields['problem'])) {
            return [[$state->get('problem'), Domain::unknown()]];
        }
        $outputs = [];
        if ($method === 'paginate') {
            $outputs[] = (new SelectCompiler($grammar))->total($state);
        }
        $outputs[] = $this->dispatch($state, $method, $arguments, $grammar);
        if ($state->get('eager')->soleLiteral()?->value === true && in_array($method, SelectCompiler::READS, true)) {
            $outputs[] = [Domain::opaque(TypeShape::of(['string']), Origin::Call, 'Eloquent eager load is not reconstructed'), Domain::unknown()];
        }

        return $outputs;
    }

    /**
     * @param list<Domain> $arguments
     * @return array{Domain, Domain} Compiles one state, including implicit SoftDeletes predicates.
     */
    public function compile(QueryState $state, string $method, array $arguments): array
    {
        $state = $this->prepare($state, $method);
        if (isset($state->fields['problem'])) {
            return [$state->get('problem'), Domain::unknown()];
        }

        return $this->dispatch($state, $method, $arguments, new Grammar($this->dialects->find($state->string('dialect'))));
    }

    /**
     * The state with the dialect checked, global scopes applied and unmodelled writes marked.
     */
    public function prepare(QueryState $state, string $method): QueryState
    {
        if ($this->dialects->find($state->string('dialect')) === null) {
            $state = $state->reject('Laravel connection dialect is unresolved');
        }
        $grammar = new Grammar($this->dialects->find($state->string('dialect')));
        if ($state->get('softDeletes')->soleLiteral()?->value === true && $state->string('trashed') !== 'withtrashed') {
            $state = (new Predicates($grammar))->groupDisjunction($state);
            $column = $state->get('table')->concat(Domain::literal('.'))->concat($state->get('deletedColumn'));
            $state = (new Predicates($grammar))->nulls($state, [$column], 'and', $state->string('trashed') === 'onlytrashed');
        }
        if ($method === 'delete' && $state->get('softDeletes')->soleLiteral()?->value === true) {
            $state = $state->reject('Eloquent soft-delete writes are not modelled');
        }
        if (in_array($method, ['update', 'increment', 'decrement'], true) && $state->get('timestamps')->soleLiteral()?->value === true) {
            $state = $state->reject('Eloquent timestamped writes are not modelled');
        }

        return $state;
    }

    /**
     * @param list<Domain> $arguments
     * @return array{Domain, Domain} The read or write compiler's statement for a prepared state.
     */
    public function dispatch(QueryState $state, string $method, array $arguments, Grammar $grammar): array
    {
        if (in_array($method, SelectCompiler::READS, true)) {
            return (new SelectCompiler($grammar))->compile($state, $method, $arguments);
        }

        return (new WriteCompiler($grammar))->compile($state, $method, $arguments);
    }
}
