<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Derivation;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer;
use SqlCatalog\Analysis\EvaluationBudget;
use SqlCatalog\Analysis\ExpressionEvaluator;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Evaluation\CallResults;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Php\FunctionShape;
use SqlCatalog\Php\MethodShape;
use SqlCatalog\Php\ParameterShape;
use SqlCatalog\Text\Origin;

/**
 * What a call into the analyzed source returns, read back from its `return` statements.
 *
 * Each `return` is walked back like any other point, with the callee's
 * parameters standing for the arguments the call passes, so only what the
 * returned value depends on is run. A call reached again with the same
 * arguments is answered from memory.
 *
 * @visibility root
 */
final class CalleeReturns
{
    private BackwardSlicer $slicer;

    private SliceExecutor $executor;

    private FreeNames $names;

    private EvaluationBudget $budget;

    private CallResults $remembered;

    /**
     * Wires the reader to the slicer and executor it reads returns with.
     */
    public function __construct(
        BackwardSlicer $slicer,
        SliceExecutor $executor,
        EvaluationBudget $budget,
        ?FreeNames $names = null,
        ?CallResults $remembered = null,
    ) {
        $this->slicer = $slicer;
        $this->executor = $executor;
        $this->budget = $budget;
        $this->names = $names ?? new FreeNames();
        $this->remembered = $remembered ?? new CallResults();
    }

    /**
     * Every value the callee can return for these arguments.
     *
     * @param list<Domain> $arguments
     */
    public function valueOf(
        MethodShape|FunctionShape $callee,
        array $arguments,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): Domain {
        $name = $callee instanceof MethodShape ? $callee->qualifiedName() : $callee->name;
        $body = $callee->node?->getStmts();
        if ($body === null) {
            return Domain::opaque($callee->returnType, Origin::Call, $name . '()');
        }
        if ($scope->depth() >= $this->budget->maxDepth || $scope->isFollowing($name) || $this->budget->isExhausted()) {
            return Domain::opaque($callee->returnType, Origin::Budget, $name . '()');
        }
        $key = $this->remembered->keyFor($name, $arguments);

        return $this->remembered->recall($key) ?? $this->read($callee, $name, $key, $arguments, $scope, $expressions);
    }

    /**
     * What the callee returns, read from its body and remembered unless the budget cut the reading short.
     *
     * @param list<Domain> $arguments
     */
    public function read(
        MethodShape|FunctionShape $callee,
        string $name,
        string $key,
        array $arguments,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): Domain {
        $inner = $scope->enter($name, $callee instanceof MethodShape ? $callee->className : $scope->className, $callee->file);
        $start = $this->parameters($callee->parameters, $arguments, $inner, $expressions);
        $result = null;
        foreach ($this->returnsIn($callee->node?->getStmts() ?? []) as $return) {
            $value = $this->returned($return, $start, $inner, $expressions);
            $result = $result === null ? $value : $result->union($value);
        }
        $result ??= Domain::literal(null);
        if (!$this->budget->isExhausted() && $this->cacheable($result)) {
            $this->remembered->remember($key, $result);
        }

        return $result;
    }

    /**
     * Allocated objects, including those inside arrays, cannot be reused across calls.
     */
    public function cacheable(Domain $value): bool
    {
        foreach ($value->terms as $term) {
            if ($term instanceof \SqlCatalog\Evaluation\ObjectTerm && $term->identity !== null) {
                return false;
            }
            if ($term instanceof \SqlCatalog\Evaluation\ArrayTerm) {
                foreach ($term->entries as $entry) {
                    if (!$this->cacheable($entry->value)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * What one `return` statement can return, starting from the given parameters.
     */
    public function returned(Stmt\Return_ $return, Environment $start, FunctionScope $scope, ExpressionEvaluator $expressions): Domain
    {
        if ($return->expr === null) {
            return Domain::literal(null);
        }
        $result = null;
        foreach ($this->slicer->sliceFrom($return, $this->names->of([$return->expr])) as $arrival) {
            $entry = $start->copy();
            foreach ($arrival->path->needs as $name => $_) {
                if (!$entry->has($name) && $name !== FreeNames::THIS && !str_contains($name, '->') && !$arrival->path->exhausted) {
                    $entry->markAbsent($name);
                }
            }
            foreach ($this->executor->run($arrival->path->forward(), $entry, $scope, $expressions) as $environment) {
                $value = $expressions->evaluate($return->expr, $environment, $scope);
                $value = Domain::fromTerms($value->terms, $value->widened, $value->combined || $environment->combined);
                if ($arrival->path->truncated || $arrival->path->exhausted) {
                    $value = $value->union(Domain::opaque($value->type(), Origin::Budget, 'incomplete return search'));
                }
                $result = $result === null ? $value : $result->union($value);
            }
        }

        return $result ?? Domain::literal(null);
    }

    /**
     * The parameters bound to the arguments, with defaults for the ones not passed.
     *
     * @param list<ParameterShape> $parameters
     * @param list<Domain> $arguments
     */
    public function parameters(array $parameters, array $arguments, FunctionScope $scope, ExpressionEvaluator $expressions): Environment
    {
        $environment = new Environment();
        foreach ($parameters as $position => $parameter) {
            $value = $parameter->variadic
                ? Domain::opaque($parameter->type, Origin::Parameter, '...$' . $parameter->name)
                : ($arguments[$position] ?? null);
            if ($value === null && $parameter->default !== null) {
                $value = $expressions->evaluate($parameter->default, new Environment(), $scope);
            }
            $environment->write($parameter->name, $value ?? Domain::opaque($parameter->type, Origin::Parameter, '$' . $parameter->name));
        }

        return $environment;
    }

    /**
     * The `return` statements of a body, leaving out those of closures and classes declared in it.
     *
     * @param array<array-key, Node> $nodes
     * @return list<Stmt\Return_>
     */
    public function returnsIn(array $nodes): array
    {
        $found = [];
        foreach ($nodes as $node) {
            if ($node instanceof Node\FunctionLike || $node instanceof Stmt\ClassLike) {
                continue;
            }
            if ($node instanceof Stmt\Return_) {
                $found[] = $node;
            }
            foreach (get_object_vars($node) as $sub) {
                $children = [];
                foreach (is_array($sub) ? $sub : [$sub] as $child) {
                    if ($child instanceof Stmt) {
                        $children[] = $child;
                    }
                }
                $found = array_merge($found, $this->returnsIn($children));
            }
        }

        return $found;
    }
}
