<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Laravel;

use PhpParser\Node\Expr;
use SqlCatalog\Core\Analysis\Derivation\Objects\CallbackEffects;
use SqlCatalog\Core\Analysis\ExpressionEvaluator;
use SqlCatalog\Core\Analysis\FunctionScope;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\Environment;
use SqlCatalog\Core\Evaluation\ObjectTerm;
use SqlCatalog\Core\Php\ProgramIndex;

/**
 * Models nested predicates and source-declared local scopes through shared callback execution.
 *
 * @visibility root
 */
final class CallbackModel
{
    /**
     * Uses source metadata and the current derivation's callback runner.
     */
    public function __construct(private readonly ProgramIndex $index, private readonly CallbackEffects $effects, private readonly \SqlCatalog\Core\Sql\Dialects $dialects = new \SqlCatalog\Core\Sql\Dialects())
    {
    }

    /**
     * A nested where callback or local scope, otherwise null.
     *
     * @param list<Domain> $arguments
     */
    public function apply(Expr\CallLike $call, ObjectTerm $object, string $method, array $arguments, Environment $environment, FunctionScope $scope, ExpressionEvaluator $expressions): ?Domain
    {
        $callback = $call->getArgs()[0]->value ?? null;
        if (in_array($method, ['where', 'orwhere'], true) && ($callback instanceof Expr\Closure || $callback instanceof Expr\ArrowFunction)) {
            if (count($arguments) !== 1) {
                return Domain::of(QueryState::from($object)->reject('Laravel nested predicate overload is not modelled')->object($object));
            }

            return $this->nested($callback, $object, $method, $environment, $scope, $expressions);
        }
        if (in_array($method, ['when', 'unless'], true)) {
            return $this->conditional($call, $object, $arguments, $environment, $scope, $expressions);
        }
        $model = QueryState::from($object)->string('model');
        $local = $this->index->findMethod($model, 'scope' . $method);
        if ($local?->node === null) {
            return null;
        }
        $inner = $scope->enter($local->qualifiedName(), $local->className, $local->file);
        if ($scope->isFollowing($local->qualifiedName())) {
            return Domain::of(QueryState::from($object)->reject('Recursive Eloquent scope')->object($object));
        }
        $result = $this->effects->apply($local->node, array_merge([Domain::of($object)], $arguments), new Environment(['this' => Domain::of(new ObjectTerm($model ?? ModelMetadata::MODEL))]), $inner, $expressions);

        return $this->checked($result, $object);
    }

    /**
     * Both outcomes of a conditional callback, since the condition is not evaluated.
     *
     * @param list<Domain> $arguments
     */
    public function conditional(Expr\CallLike $call, ObjectTerm $object, array $arguments, Environment $environment, FunctionScope $scope, ExpressionEvaluator $expressions): Domain
    {
        $branches = [];
        foreach ([1, 2] as $index) {
            $callback = $call->getArgs()[$index]->value ?? null;
            if ($callback instanceof Expr\Closure || $callback instanceof Expr\ArrowFunction) {
                $branches[] = $callback;
            }
        }
        if (count($arguments) < 2 || count($arguments) > 3 || count($branches) !== count($arguments) - 1) {
            return Domain::of(QueryState::from($object)->reject('Laravel conditional callback is not modelled')->object($object));
        }
        $terms = [];
        foreach ($branches as $branch) {
            $result = $this->effects->apply($branch, [Domain::of($object), $arguments[0]], $environment, $scope, $expressions);
            $terms = array_merge($terms, $this->closed($result, $object, 'Laravel conditional callback effects could not be closed')->terms);
        }
        if (count($branches) === 1) {
            $terms[] = $object;
        }

        return Domain::fromTerms($terms);
    }

    /**
     * The callback's states of the same builder, or a visible gap when its effects escaped or widened.
     */
    public function closed(Domain $result, ObjectTerm $original, string $reason): Domain
    {
        foreach ($result->terms as $term) {
            if ($result->widened || !$term instanceof ObjectTerm || $term->identity !== $original->identity) {
                return Domain::of(QueryState::from($original)->reject($reason)->object($original));
            }
        }

        return $result;
    }

    /**
     * Parenthesizes only the callback's predicates, then restores the outer query's fields.
     */
    public function nested(Expr\Closure|Expr\ArrowFunction $callback, ObjectTerm $object, string $method, Environment $environment, FunctionScope $scope, ExpressionEvaluator $expressions): Domain
    {
        $outer = QueryState::from($object);
        foreach ((new \SqlCatalog\Core\Analysis\Derivation\FreeNames())->read($callback) as $name => $_) {
            $captured = $environment->read($name);
            if ($captured->soleObject()?->identity !== null || $captured->soleArray() !== null) {
                (new BuilderCalls($this->index))->unsupported($captured, $environment, 'Captured callback object effects are not modelled');
                $outer = $outer->reject('Captured callback object effects are not modelled');
            }
        }
        $state = $outer->with('where', QueryState::list([]))->with('whereBindings', QueryState::list([]));
        $nested = new ObjectTerm($object->className, identity: $object->identity . ':nested:' . spl_object_id($callback), state: $state->array());
        $result = $this->effects->apply($callback, [Domain::of($nested)], $environment, $scope, $expressions);
        $terms = [];
        $grammar = new Grammar($this->dialects->find($outer->string('dialect')));
        foreach ($result->terms as $term) {
            if (!$term instanceof ObjectTerm) {
                $terms[] = $outer->reject('Nested Laravel predicate could not be read')->object($object);
                continue;
            }
            $inner = QueryState::from($term);
            $updated = $outer;
            if (isset($inner->fields['problem']) || $result->widened) {
                $updated = $updated->reject('Nested Laravel predicate has unmodelled effects');
            } elseif ($inner->items('where') !== []) {
                $predicate = Domain::literal('(')->concat($grammar->join($inner->items('where'), ' '))->concat(Domain::literal(')'));
                $updated = (new Predicates($grammar))->add($updated, $predicate, $inner->items('whereBindings'), $method === 'orwhere' ? 'or' : 'and');
            }
            $terms[] = $updated->object($object);
        }

        return Domain::fromTerms($terms, $result->widened, $result->combined);
    }

    /**
     * Leaves a visible gap when a callback loses its receiver or exceeds the budget.
     */
    public function checked(Domain $result, ObjectTerm $original): Domain
    {
        $object = $result->soleObject();
        if ($result->widened || $object === null || $object->identity !== $original->identity) {
            return Domain::of(QueryState::from($original)->reject('Eloquent scope effects could not be closed')->object($original));
        }

        foreach (QueryState::from($object)->items('where') as $predicate) {
            $literal = $predicate->soleLiteral()?->value;
            if (!is_string($literal) || str_starts_with($literal, 'or ')) {
                return Domain::of(QueryState::from($original)->reject('Eloquent scope boolean regrouping is not modelled')->object($original));
            }
        }

        return $result;
    }
}
