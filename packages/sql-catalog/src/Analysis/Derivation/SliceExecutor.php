<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Derivation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use SqlCatalog\Analysis\Derivation\Slice\SliceStep;
use SqlCatalog\Analysis\Effect\WriteEffects;
use SqlCatalog\Analysis\EvaluationBudget;
use SqlCatalog\Analysis\ExpressionEvaluator;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Php\DeclaredGlobals;
use SqlCatalog\Php\NodeText;
use SqlCatalog\Php\TypeReader;
use SqlCatalog\Text\Origin;
use SqlCatalog\Type\TypeShape;
use WeakMap;

/**
 * Runs the steps a path kept, in order, and reports the values they leave behind.
 *
 * Only the assignments the call depends on are run, so the work is what the
 * statement needs rather than what the body does. When an assignment leaves a
 * variable with several possible values — a ternary, a call that returns one
 * of several strings — the run splits there, one run per value, so every later
 * use of the variable sees the same one. That is what stops `ORDER BY $o, id $o`
 * from pairing an ascending first column with a descending second.
 *
 * Keeping runs apart costs one evaluation of every later step per run, so how
 * many are kept apart is what the budget can pay for along the path: a short
 * path keeps a dozen, a body of several hundred assignments under a hundred
 * conditionals keeps a few, and joins the rest value by value. A joined run
 * still holds every value; what it gives up is knowing which values go
 * together.
 *
 * @visibility root
 */
final class SliceExecutor
{
    /**
     * How many runs are kept apart before the rest are joined.
     */
    public const MAX_RUNS = 12;

    /**
     * How many expressions running one step is expected to evaluate, for sizing how many runs a path can afford.
     */
    public const STEP_COST = 8;

    private DeclaredGlobals $globals;

    private TypeReader $types;

    private ModifiedNames $modified;

    private NodeText $text;

    private EvaluationBudget $budget;

    /**
     * Wires the executor to what it needs to bind declarations.
     */
    public function __construct(
        ?DeclaredGlobals $globals = null,
        ?TypeReader $types = null,
        ?ModifiedNames $modified = null,
        ?NodeText $text = null,
        ?EvaluationBudget $budget = null,
    ) {
        $this->globals = $globals ?? new DeclaredGlobals();
        $this->types = $types ?? new TypeReader();
        $this->modified = $modified ?? new ModifiedNames();
        $this->text = $text ?? new NodeText();
        $this->budget = $budget ?? new EvaluationBudget();
    }

    /**
     * The environments the steps leave behind, one per run.
     *
     * A run that works out something on the way to a statement — what a
     * callee returns, what a caller passes, what a property holds — stops when
     * the search's budget is spent. The run that reads the statement itself
     * goes on until the reading limit, so what was found along the way is not
     * thrown away for want of the last few steps.
     *
     * @param list<SliceStep> $steps The steps, in the order they run
     * @param bool $closing Whether this run reads the statement itself rather than something on the way to it
     * @param int|null $limit How many runs to keep apart, or null to size it from the budget
     * @return list<Environment>
     */
    public function run(
        array $steps,
        Environment $start,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
        bool $closing = false,
        ?int $limit = null,
    ): array {
        $limit ??= $this->affordableRuns($steps);
        /** @var WeakMap<Node, int> $passes */
        $passes = new WeakMap();
        /** @var WeakMap<Node, Domain> $iterables */
        $iterables = new WeakMap();
        $environments = [$start];
        foreach ($steps as $index => $step) {
            if ($closing ? $this->budget->isSpent() : $this->budget->isExhausted()) {
                return $this->abandon(array_slice($steps, $index), $environments);
            }
            $next = [];
            foreach ($environments as $environment) {
                $next = array_merge($next, $this->apply($step, $environment, $scope, $expressions, $passes, $iterables, $limit));
            }
            $environments = $this->bound($next, $limit);
        }

        return $environments;
    }

    /**
     * How many runs the budget can keep apart along these steps.
     *
     * @param list<SliceStep> $steps
     */
    public function affordableRuns(array $steps): int
    {
        $size = max(1, $this->size($steps));

        return max(1, min(self::MAX_RUNS, intdiv($this->budget->maxSteps, $size * self::STEP_COST)));
    }

    /**
     * How many steps there are, counting the steps held as alternatives.
     *
     * @param list<SliceStep> $steps
     */
    public function size(array $steps): int
    {
        $size = 0;
        foreach ($steps as $step) {
            $size++;
            foreach ($step->alternatives as $alternative) {
                $size += $this->size($alternative);
            }
        }

        return $size;
    }

    /**
     * The environments with everything the steps not yet run would write left open.
     *
     * Stopping partway must not report a variable as holding what it held
     * before a later assignment the run never got to: that would be a wrong
     * statement rather than an incomplete one. So every name a remaining step
     * writes is left open, marked as stopped by the budget.
     *
     * @param list<SliceStep> $steps
     * @param list<Environment> $environments
     * @return list<Environment>
     */
    public function abandon(array $steps, array $environments): array
    {
        $names = [];
        foreach ($steps as $step) {
            $names += $this->writtenBy($step);
        }
        $open = [];
        foreach ($environments as $environment) {
            $left = $environment->copy();
            (new WriteEffects())->apply($names, $left, Origin::Budget);
            $open[] = $left;
        }

        return $open;
    }

    /**
     * Every name a step may write, including the steps it holds as alternatives.
     *
     * @return array<string, true>
     */
    public function writtenBy(SliceStep $step): array
    {
        if ($step->node !== null) {
            $names = $this->modified->of($step->node);
            foreach ($step->names as $name) {
                $names[$name] = true;
            }

            return $names;
        }
        $names = [];
        foreach ($step->alternatives as $alternative) {
            foreach ($alternative as $inner) {
                $names += $this->writtenBy($inner);
            }
        }

        return $names;
    }

    /**
     * The environments one step leaves behind, starting from one environment.
     *
     * @param WeakMap<Node, int> $passes How many passes each loop has started so far
     * @param WeakMap<Node, Domain> $iterables What each loop went over, as read before its first pass
     * @return list<Environment>
     */
    public function apply(
        SliceStep $step,
        Environment $environment,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
        WeakMap $passes,
        WeakMap $iterables,
        int $limit = self::MAX_RUNS,
    ): array {
        if ($step->node === null) {
            $runs = [];
            foreach ($step->alternatives as $alternative) {
                $runs = array_merge($runs, $this->run($alternative, $environment->copy(), $scope, $expressions));
            }

            return $runs;
        }
        $next = $environment->copy();
        $node = $step->node;
        if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            $this->enterClosure($node, $step->names, $next);

            return [$next];
        }
        if ($node instanceof Stmt\Global_ || $node instanceof Stmt\Static_ || $node instanceof Stmt\Unset_) {
            $this->declare($node, $next);

            return [$next];
        }
        if ($node instanceof Stmt\Foreach_) {
            $pass = $passes[$node] ?? 0;
            $passes[$node] = $pass + 1;
            $iterable = $iterables[$node] ?? $expressions->evaluate($node->expr, $next, $scope);
            $iterables[$node] = $iterable;
            $this->iterate($node, $iterable, $pass, $next, $scope, $expressions);

            return $this->split($next, $this->modified->of($node), $limit);
        }
        if ($node instanceof Expr) {
            $this->assign($node, $next, $scope, $expressions);

            return $this->split($next, $this->modified->of($node), $limit);
        }

        return [$next];
    }

    /**
     * Binds what a closure defines for itself: its parameters, and the names it reads without defining.
     *
     * @param list<string> $names
     */
    public function enterClosure(Expr\Closure|Expr\ArrowFunction $closure, array $names, Environment $environment): void
    {
        $types = [];
        foreach ($closure->params as $parameter) {
            if ($parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                $types[$parameter->var->name] = $this->types->read($parameter->type);
            }
        }
        foreach ($names as $name) {
            if (isset($types[$name])) {
                $environment->write($name, Domain::opaque($types[$name], Origin::Parameter, '$' . $name));
            } else {
                $environment->markAbsent($name);
            }
        }
    }

    /**
     * Binds what a `global`, `static` or `unset` declaration gives the names it lists.
     */
    public function declare(Stmt\Global_|Stmt\Static_|Stmt\Unset_ $declaration, Environment $environment): void
    {
        if ($declaration instanceof Stmt\Unset_) {
            foreach ($declaration->vars as $variable) {
                if ($variable instanceof Expr\Variable && is_string($variable->name)) {
                    $environment->markAbsent($variable->name);
                }
            }

            return;
        }
        foreach ($this->modified->own($declaration) as $name => $_) {
            $className = $declaration instanceof Stmt\Global_ ? $this->globals->classOf($declaration, $name) : null;
            $environment->write($name, $className !== null
                ? Domain::of(new ObjectTerm($className))
                : Domain::opaque(TypeShape::unknown(), Origin::Unresolved, ($declaration instanceof Stmt\Global_ ? 'global $' : 'static $') . $name));
        }
    }

    /**
     * Binds a `foreach`'s variables for one pass.
     *
     * An array written out in full gives each pass its own element; anything
     * else gives every pass whatever an element of it can be.
     */
    public function iterate(
        Stmt\Foreach_ $loop,
        Domain $iterable,
        int $pass,
        Environment $environment,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): void {
        $array = $iterable->soleArray();
        $entry = $array === null ? null : ($array->entries[$pass] ?? null);
        $value = $entry === null ? $this->anyElement($iterable) : $entry->value;
        $key = $entry === null
            ? Domain::opaque(TypeShape::of(['int', 'string']), Origin::Loop, 'iterated key')
            : ($entry->key ?? Domain::literal($pass));

        $expressions->references()->assign($loop->valueVar, $value, $environment, $scope, $expressions);
        if ($loop->keyVar !== null) {
            $expressions->references()->assign($loop->keyVar, $key, $environment, $scope, $expressions);
        }
    }

    /**
     * Whatever an element of an iterable can be.
     */
    public function anyElement(Domain $iterable): Domain
    {
        return $iterable->soleArray()?->anyValue(Origin::Loop, 'iterated value')
            ?? Domain::opaque(TypeShape::unknown(), Origin::Loop, 'iterated value');
    }

    /**
     * Runs one assignment, including the kinds the expression evaluator does not write back.
     */
    public function assign(Expr $assignment, Environment $environment, FunctionScope $scope, ExpressionEvaluator $expressions): void
    {
        $value = $expressions->evaluate($assignment, $environment, $scope);
        $target = $assignment instanceof Expr\Assign || $assignment instanceof Expr\AssignOp
            || $assignment instanceof Expr\AssignRef || $assignment instanceof Expr\PreInc
            || $assignment instanceof Expr\PostInc || $assignment instanceof Expr\PreDec
            || $assignment instanceof Expr\PostDec ? $assignment->var : null;
        if ($target === null || $assignment instanceof Expr\Assign || $assignment instanceof Expr\AssignOp\Concat) {
            return;
        }
        $written = $assignment instanceof Expr\AssignRef
            ? $value
            : Domain::opaque($value->type(), Origin::Unresolved, $this->text->render($assignment));
        $expressions->references()->assign($target, $written, $environment, $scope, $expressions);
    }

    /**
     * The environment split into one per value of the names just written, when any has several.
     *
     * Bound each name's expansion before splitting the next one. Waiting until
     * every name has been split materializes their full Cartesian product,
     * even though only a handful of runs will survive the bound.
     *
     * @param array<string, true> $names
     * @return list<Environment>
     */
    public function split(Environment $environment, array $names, int $limit = self::MAX_RUNS): array
    {
        if ($limit < 2) {
            return [$environment];
        }
        $environments = [$environment];
        foreach ($names as $name => $_) {
            if (!$environment->has($name)) {
                continue;
            }
            $domain = $environment->read($name);
            if ($domain->widened || count($domain->terms) < 2) {
                continue;
            }
            $split = [];
            foreach ($environments as $current) {
                foreach ($domain->terms as $term) {
                    $one = $current->copy();
                    $one->narrow($name, Domain::of($term));
                    $split[] = $one;
                }
            }
            $environments = $this->bound($split, $limit);
        }

        return $this->bound($environments, $limit);
    }

    /**
     * The environments with repeats dropped and the rest joined once there are too many to keep apart.
     *
     * @param list<Environment> $environments
     * @return list<Environment>
     */
    public function bound(array $environments, int $limit = self::MAX_RUNS): array
    {
        $unique = [];
        foreach ($environments as $environment) {
            $unique[$environment->signature()] ??= $environment;
        }
        $unique = array_values($unique);
        if (count($unique) <= $limit) {
            return $unique;
        }
        $kept = array_slice($unique, 0, $limit - 1);
        $joined = null;
        foreach (array_slice($unique, $limit - 1) as $environment) {
            $joined = $joined === null ? $environment : $joined->join($environment);
        }
        if ($joined !== null) {
            $kept[] = $joined;
        }

        return $kept;
    }
}
