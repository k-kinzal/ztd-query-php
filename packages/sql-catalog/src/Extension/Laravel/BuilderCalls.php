<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Laravel;

use PhpParser\Node;
use PhpParser\Node\Expr;
use SqlCatalog\Core\Analysis\ExpressionEvaluator;
use SqlCatalog\Core\Analysis\FunctionScope;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\Environment;
use SqlCatalog\Core\Evaluation\ObjectTerm;
use SqlCatalog\Core\Php\ProgramIndex;

/**
 * Laravel factories and mutations executed by the ordinary expression evaluator.
 *
 * @visibility root
 */
final class BuilderCalls
{
    /**
     * The framework contract identified by query.
     */
    public const QUERY = 'Illuminate\Database\Query\Builder';

    /**
     * The framework contract identified by connection.
     */
    public const CONNECTION = 'Illuminate\Database\Connection';

    /**
     * The framework contract identified by facade.
     */
    public const FACADE = 'Illuminate\Support\Facades\DB';

    private int $allocations = 0;

    /**
     * Configures the source metadata and SQL grammar used by this model.
     */
    public function __construct(private readonly ProgramIndex $index, private readonly ?string $dialect = null, private readonly ?CallbackModel $callbacks = null, private readonly \SqlCatalog\Core\Sql\Dialects $dialects = new \SqlCatalog\Core\Sql\Dialects())
    {
    }

    /**
     * Whether a class is a query builder, Eloquent builder or Eloquent model.
     */
    public function isBuilder(?string $className): bool
    {
        return $this->index->isInstanceOf($className, self::QUERY)
            || $this->index->isInstanceOf($className, ModelMetadata::BUILDER)
            || (new ModelMetadata($this->index))->recognizes($className);
    }

    /**
     * @param list<Domain> $arguments A modelled static factory, or null for other classes.
     */
    public function staticCall(string $className, string $method, array $arguments, Environment $environment, ?Expr\CallLike $call = null, ?FunctionScope $scope = null, ?ExpressionEvaluator $expressions = null): ?Domain
    {
        $method = strtolower($method);
        if (strcasecmp($className, self::FACADE) === 0) {
            return $this->connectionCall($method, $arguments, new QueryState(['dialect' => Domain::literal($this->dialect)]), $environment);
        }
        if (!(new ModelMetadata($this->index))->recognizes($className)) {
            return null;
        }
        $object = $this->allocate(ModelMetadata::BUILDER, (new ModelMetadata($this->index))->state($className, $this->dialect));
        if ($call !== null && $scope !== null && $expressions !== null) {
            $callback = $this->callback($call, $object, $method, $arguments, $environment, $scope, $expressions);
            if ($callback !== null) {
                return $callback;
            }
        }

        return $this->mutate($object, $method, $arguments, $environment);
    }

    /**
     * @param list<Domain> $arguments A modelled connection or builder method, or null.
     */
    public function methodCall(Domain $receiver, string $method, array $arguments, Environment $environment, ?Expr\CallLike $call = null, ?FunctionScope $scope = null, ?ExpressionEvaluator $expressions = null): ?Domain
    {
        $class = $receiver->type()->soleClassName();
        $method = strtolower($method);
        if ($this->isConnection($class)) {
            $object = $receiver->soleObject();
            $state = $object?->state === null ? new QueryState(['dialect' => Domain::literal($this->connectionDialect($class))]) : QueryState::from($object);

            return $this->connectionCall($method, $arguments, $state, $environment);
        }
        if (!$this->isBuilder($class)) {
            return null;
        }
        $results = [];
        foreach ($receiver->terms as $term) {
            $object = $term instanceof ObjectTerm ? $term : new ObjectTerm($class ?? self::QUERY);
            $callback = $call !== null && $scope !== null && $expressions !== null ? $this->callback($call, $object, $method, $arguments, $environment, $scope, $expressions) : null;
            $results = array_merge($results, ($callback ?? $this->mutate($object, $method, $arguments, $environment))->terms);
        }

        $result = Domain::fromTerms($results, $receiver->widened, $receiver->combined);
        $environment->objects()->rememberValue($result);

        return $result;
    }

    /**
     * Applies framework callback semantics while retaining the ordinary object's identity.
     *
     * @param list<Domain> $arguments
     */
    public function callback(Expr\CallLike $call, ObjectTerm $object, string $method, array $arguments, Environment $environment, FunctionScope $scope, ExpressionEvaluator $expressions): ?Domain
    {
        $value = $this->callbacks?->apply($call, $object, $method, $arguments, $environment, $scope, $expressions);
        if ($value !== null) {
            $environment->objects()->rememberValue($value);
        }

        return $value;
    }

    /**
     * Whether the receiver has a built-in Illuminate connection contract.
     */
    public function isConnection(?string $class): bool
    {
        return $this->index->isInstanceOf($class, self::CONNECTION) || $this->dialects->hasConnection($class);
    }

    /**
     * The grammar implied by a concrete connection, otherwise the configured grammar.
     */
    public function connectionDialect(?string $class): ?string
    {
        return $this->dialects->connection($class, $this->dialect);
    }

    /**
     * @param list<Domain> $arguments Connection factories and raw expressions.
     */
    public function connectionCall(string $method, array $arguments, QueryState $state, Environment $environment): ?Domain
    {
        if ($method === 'raw' && count($arguments) === 1) {
            return Domain::of($this->allocate('Illuminate\\Database\\Query\\Expression', new QueryState(['sql' => $arguments[0]])));
        }
        if ($method === 'connection' && count($arguments) <= 1) {
            return Domain::of($this->allocate(self::CONNECTION, $state));
        }
        if ($method === 'table' && count($arguments) === 1) {
            $object = $this->allocate(self::QUERY, $state->with('table', $arguments[0])->with('key', Domain::literal('id')));
            $environment->objects()->remember($object);

            return Domain::of($object);
        }

        return null;
    }

    /**
     * A fresh identity even when the same allocation executes repeatedly.
     */
    public function allocate(string $class, QueryState $state): ObjectTerm
    {
        $this->allocations++;

        return new ObjectTerm($class, identity: 'laravel:' . $this->allocations, state: $state->array());
    }

    /**
     * @param list<Domain> $arguments Applies one effect, preserving identity and any earlier gaps.
     */
    public function mutate(ObjectTerm $object, string $method, array $arguments, Environment $environment): Domain
    {
        $state = QueryState::from($object);
        $grammar = new Grammar($this->dialects->find($state->string('dialect')));
        if ($method === 'query' && $arguments === []) {
            $updated = $state;
        } elseif (in_array($method, ['withtrashed', 'onlytrashed'], true) && $arguments === [] && $state->get('softDeletes')->soleLiteral()?->value === true) {
            $updated = $state->with('trashed', Domain::literal($method));
        } else {
            $updated = (new Predicates($grammar))->apply($state, $method, $arguments)
                ?? (new Clauses($grammar))->apply($state, $method, $arguments)
                ?? $state->reject('Unmodelled Laravel effect: ' . $method);
        }
        $object = $updated->object($object);
        $environment->objects()->remember($object);

        return Domain::of($object);
    }

    /**
     * Retains terminal calls' persistent mutations and identifies their return values.
     *
     * @param list<Domain> $arguments
     */
    public function execution(Domain $receiver, string $method, array $arguments, Environment $environment): Domain
    {
        $snapshots = [];
        foreach ($receiver->terms as $term) {
            if (!$term instanceof ObjectTerm) {
                continue;
            }
            $state = QueryState::from($term);
            if (in_array($method, ['first', 'firstorfail', 'find'], true)) {
                $state = $state->with('limit', Domain::literal(1));
                if ($method === 'find') {
                    $state = (new Predicates(new Grammar($this->dialects->find($state->string('dialect')))))->basic($state, [$state->get('key'), $arguments[0] ?? Domain::unknown()], 'and');
                }
                $snapshots[] = $state->object($term);
            } elseif (!in_array($method, ['get', 'all', 'pluck', 'count', 'sum', 'avg', 'min', 'max', 'exists', 'doesntexist', 'insert', 'insertorignore', 'update', 'delete'], true)) {
                $snapshots[] = $state->reject('Unmodelled Laravel execution effects')->object($term);
            }
        }
        $environment->objects()->rememberValue(Domain::fromTerms($snapshots, $receiver->widened, $receiver->combined));
        $model = $receiver->soleObject() === null ? null : QueryState::from($receiver->soleObject())->string('model');
        $type = match ($method) {
            'get', 'all', 'pluck' => 'Illuminate\\Support\\Collection',
            'first', 'firstorfail', 'find' => $model ?? 'stdClass',
            'exists', 'doesntexist', 'insert', 'insertorignore' => 'bool',
            'count', 'update', 'delete', 'insertgetid' => 'int',
            default => 'mixed',
        };

        return Domain::opaque(\SqlCatalog\Core\Type\TypeShape::of([$type]), \SqlCatalog\Core\Text\Origin::Call, 'Laravel ' . $method);
    }

    /**
     * @param list<Node\Arg> $arguments Named/unpacked calls need argument normalization before modelling.
     */
    public function positional(array $arguments): bool
    {
        foreach ($arguments as $argument) {
            if ($argument->name !== null || $argument->unpack) {
                return false;
            }
        }

        return true;
    }

    /**
     * A bad overload keeps the builder identity so its eventual execution remains visible.
     */
    public function unsupported(Domain $receiver, Environment $environment, string $reason): Domain
    {
        $terms = [];
        foreach ($receiver->terms as $term) {
            if ($term instanceof ObjectTerm && $term->identity !== null) {
                $term = QueryState::from($term)->reject($reason)->object($term);
                $environment->objects()->remember($term);
            }
            if ($term instanceof \SqlCatalog\Core\Evaluation\ArrayTerm) {
                $term = new \SqlCatalog\Core\Evaluation\ArrayTerm(array_map(fn (\SqlCatalog\Core\Evaluation\ArrayEntry $entry): \SqlCatalog\Core\Evaluation\ArrayEntry => new \SqlCatalog\Core\Evaluation\ArrayEntry($entry->key, $this->unsupported($entry->value, $environment, $reason)), $term->entries), $term->complete);
            }
            $terms[] = $term;
        }

        $result = Domain::fromTerms($terms, $receiver->widened, $receiver->combined);
        $environment->objects()->rememberValue($result);

        return $result;
    }
}
