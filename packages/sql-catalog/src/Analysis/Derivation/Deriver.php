<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Derivation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use SqlCatalog\Analysis\Derivation\Slice\Arrival;
use SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer;
use SqlCatalog\Analysis\EvaluationBudget;
use SqlCatalog\Analysis\ExpressionEvaluator;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Evaluation\Domain;

/**
 * Works out what expressions can be at a point, starting from the point.
 *
 * The walk back from the point gives the paths through the body and what each
 * still needs at the body's start. A parameter still needed is looked for at
 * every call of the body, where the same question is asked again about the
 * arguments; each call that answers is one more way in. Then every way in is
 * run forward along its path, and the expressions are read at the point. What
 * is still not found at the top — a parameter nothing calls with, a global, a
 * name the budget stopped short of — is left open, saying why.
 *
 * @visibility root
 */
final class Deriver
{
    /**
     * How many callers of one body are asked, before the rest are left out and the search marked as cut short.
     */
    public const MAX_CALLERS = 12;

    /**
     * How many ways of reaching one point are kept.
     */
    public const MAX_SOLUTIONS = 32;

    private SourceTree $tree;

    private BackwardSlicer $slicer;

    private SliceExecutor $executor;

    private FreeNames $names;

    private EvaluationBudget $budget;

    private ExpressionEvaluator $expressions;

    private EntryBinder $binder;

    /**
     * @var array<string, list<Solution>>
     */
    private array $remembered = [];

    /**
     * Wires the deriver to the source and the evaluator it reads values with.
     */
    public function __construct(
        SourceTree $tree,
        BackwardSlicer $slicer,
        SliceExecutor $executor,
        ExpressionEvaluator $expressions,
        EntryBinder $binder,
        EvaluationBudget $budget,
        ?FreeNames $names = null,
    ) {
        $this->tree = $tree;
        $this->slicer = $slicer;
        $this->executor = $executor;
        $this->expressions = $expressions;
        $this->budget = $budget;
        $this->names = $names ?? new FreeNames();
        $this->binder = $binder;
    }

    /**
     * What binds the names a path still needs at the start of its body.
     */
    public function binder(): EntryBinder
    {
        return $this->binder;
    }

    /**
     * The evaluator values are read with.
     */
    public function evaluator(): ExpressionEvaluator
    {
        return $this->expressions;
    }

    /**
     * Every way the expressions can be at the point, each with one value per expression.
     *
     * @param list<Expr> $goals
     * @return list<Solution>
     */
    public function solve(Node $point, array $goals, int $depth = 0): array
    {
        $key = spl_object_id($point) . ':' . $depth . ':' . implode(',', array_map('spl_object_id', $goals));
        if (isset($this->remembered[$key])) {
            return $this->remembered[$key];
        }
        $this->remembered[$key] = [];
        $solutions = $this->run($this->slicer->sliceFrom($point, $this->names->of($goals)), $goals, $this->scopeOf($point), $depth);
        if ($this->budget->isExhausted()) {
            unset($this->remembered[$key]);
        } else {
            $this->remembered[$key] = $solutions;
        }

        return $solutions;
    }

    /**
     * Every way the expressions can be when a body finishes, by returning or by running off its end.
     *
     * @param list<Expr> $goals Expressions that read nothing but what the body leaves behind, such as a property of `$this`
     * @return list<Solution>
     */
    public function solveAtExits(FunctionLike $body, array $goals, int $depth): array
    {
        $key = 'exits:' . spl_object_id($body) . ':' . $depth . ':' . implode(',', array_map('spl_object_id', $goals));
        if (isset($this->remembered[$key])) {
            return $this->remembered[$key];
        }
        $this->remembered[$key] = [];
        $needs = $this->names->of($goals);
        $arrivals = $this->slicer->sliceFromEnd($body, $needs);
        foreach ((new NodeFinder())->findInstanceOf($body->getStmts() ?? [], Stmt\Return_::class) as $return) {
            if ($this->tree->bodyOf($return) === $body) {
                $arrivals = array_merge($arrivals, $this->slicer->sliceFrom($return, $needs));
            }
        }
        $name = $this->nameOf($body);
        $scope = new FunctionScope($this->tree->fileOf($body), $name, $this->classOf($body), [$name]);
        $solutions = $this->run($arrivals, $goals, $scope, $depth);
        if (!$this->budget->isExhausted()) {
            $this->remembered[$key] = $solutions;
        }

        return $solutions;
    }

    /**
     * The paths that arrived at a body's start, run forward along every way in, with the expressions read at their end.
     *
     * @param list<Arrival> $arrivals
     * @param list<Expr> $goals
     * @return list<Solution>
     */
    public function run(array $arrivals, array $goals, FunctionScope $scope, int $depth): array
    {
        $solutions = [];
        foreach ($arrivals as $arrival) {
            $steps = $arrival->path->forward();
            $affordable = $this->executor->affordableRuns($steps);
            $bindings = $this->binder->affordable($this->binder->bindings($arrival, $depth, $this), $affordable);
            $each = max(1, intdiv($affordable, count($bindings)));
            foreach ($bindings as $binding) {
                $runs = $this->executor->run($steps, $binding->environment, $scope, $this->expressions, $depth === 0, $each);
                foreach ($runs as $environment) {
                    $solutions[] = new Solution(
                        array_map(fn (Expr $goal): Domain => $this->expressions->evaluate($goal, $environment, $scope), $goals),
                        $binding->through,
                        $arrival->path->truncated || $binding->truncated,
                        $binding->combined,
                    );
                }
            }
        }

        return $this->bounded($solutions);
    }

    /**
     * Whether the budget is spent, so no further search should start.
     */
    public function spent(): bool
    {
        return $this->budget->isExhausted();
    }

    /**
     * The same as solving, or nothing at all once the budget is spent.
     *
     * @param list<Expr> $goals
     * @return list<Solution>
     */
    public function solveUnlessSpent(Node $point, array $goals, int $depth): array
    {
        return $this->budget->isExhausted() ? [] : $this->solve($point, $goals, $depth);
    }

    /**
     * The solutions, cut to the limit and marked when they had to be.
     *
     * @param list<Solution> $solutions
     * @return list<Solution>
     */
    public function bounded(array $solutions): array
    {
        if (count($solutions) <= self::MAX_SOLUTIONS) {
            return $solutions;
        }

        return array_map(
            static fn (Solution $solution): Solution => new Solution($solution->values, $solution->through, true, $solution->combined),
            array_slice($solutions, 0, self::MAX_SOLUTIONS),
        );
    }

    /**
     * The scope a point is evaluated in: its file, its function and its class.
     */
    public function scopeOf(Node $point): FunctionScope
    {
        $body = $this->tree->bodyOf($point);
        while ($body instanceof Expr\Closure || $body instanceof Expr\ArrowFunction) {
            $body = $this->tree->bodyOf($body);
        }
        $name = $body === null ? FunctionScope::MAIN : $this->nameOf($body);

        return new FunctionScope($this->tree->fileOf($point), $name, $this->classOf($point), [$name]);
    }

    /**
     * The class a node is written in, or null when it is written in none.
     */
    public function classOf(Node $node): ?string
    {
        $parent = $node->getAttribute('parent');
        while ($parent instanceof Node) {
            if ($parent instanceof Stmt\ClassLike) {
                return $parent->namespacedName?->toString() ?? $parent->name?->toString();
            }
            $parent = $parent->getAttribute('parent');
        }

        return null;
    }

    /**
     * The name a body is reported under.
     */
    public function nameOf(FunctionLike $body): string
    {
        if ($body instanceof Stmt\ClassMethod) {
            return ($this->classOf($body) ?? '') . '::' . $body->name->toString();
        }
        if ($body instanceof Stmt\Function_) {
            return $body->namespacedName?->toString() ?? $body->name->toString();
        }

        return ($this->classOf($body) ?? FunctionScope::MAIN) . '::{closure}';
    }
}
