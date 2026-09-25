<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Laravel;

use PhpParser\Node\Expr;
use SqlCatalog\Analysis\SinkFinder;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Extension\Model\QueryModelInterface;
use SqlCatalog\Extension\Model\QueryOutput;
use SqlCatalog\Php\ProgramIndex;

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
    public function __construct(private readonly ProgramIndex $index)
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
            [$sql, $bindings] = $this->compile($state, strtolower($method), $values);
            $outputs[] = new QueryOutput($sql, $bindings, $target->widened, $target->combined);
        }

        return $outputs;
    }

    /**
     * @param list<Domain> $arguments
     * @return array{Domain, Domain} Compiles one state, including implicit SoftDeletes predicates.
     */
    public function compile(QueryState $state, string $method, array $arguments): array
    {
        if (!in_array($state->string('dialect'), ['mysql', 'pgsql', 'sqlite'], true)) {
            $state = $state->reject('Laravel connection dialect is unresolved');
        }
        $grammar = new Grammar($state->string('dialect'));
        if ($state->get('softDeletes')->soleLiteral()?->value === true && $state->string('trashed') !== 'withtrashed') {
            $state = (new Predicates($grammar))->groupDisjunction($state);
            $column = $state->get('table')->concat(Domain::literal('.'))->concat($state->get('deletedColumn'));
            $state = (new Predicates($grammar))->nulls($state, [$column], 'and', $state->string('trashed') === 'onlytrashed');
        }
        if ($method === 'delete' && $state->get('softDeletes')->soleLiteral()?->value === true) {
            $state = $state->reject('Eloquent soft-delete writes are not modelled');
        }
        if ($method === 'update' && $state->get('timestamps')->soleLiteral()?->value === true) {
            $state = $state->reject('Eloquent timestamped writes are not modelled');
        }
        if (isset($state->fields['problem'])) {
            return [$state->get('problem'), Domain::unknown()];
        }
        if (in_array($method, ['get', 'all', 'first', 'firstorfail', 'find', 'pluck', 'count', 'sum', 'avg', 'min', 'max', 'exists', 'doesntexist'], true)) {
            return (new SelectCompiler($grammar))->compile($state, $method, $arguments);
        }

        return (new WriteCompiler($grammar))->compile($state, $method, $arguments);
    }
}
