<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Analysis\Derivation\Objects;

use PhpParser\Node;
use PhpParser\Node\Expr;
use SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer;
use SqlCatalog\Core\Analysis\Derivation\Slice\Pending;
use SqlCatalog\Core\Analysis\Derivation\SliceExecutor;
use SqlCatalog\Core\Analysis\ExpressionEvaluator;
use SqlCatalog\Core\Analysis\FunctionScope;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\Environment;

/**
 * Runs a callback's object dependencies through the same slicer as its caller.
 *
 * @visibility root
 */
final class CallbackEffects
{
    /**
     * Uses the caller's shared derivation budget and execution semantics.
     */
    public function __construct(private readonly BackwardSlicer $slicer, private readonly SliceExecutor $executor)
    {
    }

    /**
     * Reads the first parameter's state after the callback's possible effects.
     *
     * @param list<Domain> $arguments
     */
    public function apply(Node\FunctionLike $callback, array $arguments, Environment $outer, FunctionScope $scope, ExpressionEvaluator $expressions): Domain
    {
        if (!$this->supported($callback)) {
            return Domain::unknown('Callback control flow is not modelled');
        }
        $start = $this->bind($callback, $arguments, $outer);
        $parameter = $callback->getParams()[0]->var ?? null;
        if (!$parameter instanceof Expr\Variable || !is_string($parameter->name)) {
            return Domain::unknown('Callback has no named receiver parameter');
        }
        $needs = [$parameter->name => true];
        $identity = $start->read($parameter->name)->soleObject()?->identity;
        foreach ($start->names() as $name) {
            if ($identity !== null && $start->read($name)->soleObject()?->identity === $identity) {
                $needs[$name] = true;
            }
        }
        if ($callback instanceof Expr\ArrowFunction) {
            $expressions->evaluate($callback->expr, $start, $scope);

            return $start->read($parameter->name);
        }
        $statements = array_values($callback->getStmts() ?? []);
        $terms = [];
        $widened = false;
        foreach ($this->slicer->walkList($statements, count($statements), [Pending::needing($needs)]) as $path) {
            foreach ($this->executor->run($path->forward(), $start->copy(), $scope, $expressions) as $environment) {
                $value = $environment->read($parameter->name);
                $terms = array_merge($terms, $value->terms);
                $widened = $widened || $path->truncated || $value->widened;
            }
        }

        return Domain::fromTerms($terms, $widened);
    }

    /**
     * Only terminal returns of the same receiver can be interpreted as mutations.
     */
    public function supported(Node\FunctionLike $callback): bool
    {
        $parameter = $callback->getParams()[0]->var ?? null;
        if (!$parameter instanceof Expr\Variable || !is_string($parameter->name)) {
            return false;
        }
        $statements = $callback->getStmts() ?? [];
        $returns = (new \PhpParser\NodeFinder())->findInstanceOf($statements, Node\Stmt\Return_::class);
        foreach ($returns as $return) {
            if ($return !== end($statements)) {
                return false;
            }
            if ($return->expr !== null && (new ObjectEffects())->root($return->expr) !== $parameter->name
                && !($return->expr instanceof Expr\ConstFetch && strtolower($return->expr->name->toString()) === 'null')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Binds invocation arguments and only the variables a closure captures.
     *
     * @param list<Domain> $arguments
     */
    public function bind(Node\FunctionLike $callback, array $arguments, Environment $outer): Environment
    {
        $environment = $outer->copy();
        if ($callback instanceof Expr\Closure) {
            $captured = ['this'];
            foreach ($callback->uses as $use) {
                if (is_string($use->var->name)) {
                    $captured[] = $use->var->name;
                }
            }
            foreach (array_diff($environment->names(), $captured) as $name) {
                $environment->forget($name);
            }
        }
        foreach ($callback->getParams() as $position => $parameter) {
            if ($parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                $environment->write($parameter->var->name, $arguments[$position] ?? Domain::unknown('Callback argument'));
            }
        }

        return $environment;
    }
}
