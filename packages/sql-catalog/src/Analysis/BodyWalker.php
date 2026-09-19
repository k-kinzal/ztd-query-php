<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\PathSet;
use SqlCatalog\Text\Origin;
use SqlCatalog\Type\TypeShape;

/**
 * Walks a function body along every path through it, keeping the paths apart.
 *
 * A branch forks the paths rather than merging what its arms leave behind, so
 * the values one arm assigns stay together. That is what makes a branch which
 * sets both a table and a column produce the two statements it can produce
 * instead of the four that pairing the values independently would suggest.
 *
 * A loop is walked twice per path and then widened, which turns the familiar
 * `$sql .= ' AND …'` into one shape covering every number of iterations.
 *
 * @visibility root
 */
final class BodyWalker
{
    private ExpressionEvaluator $expressions;

    private EvaluationBudget $budget;

    /**
     * Wires the walker to the evaluator it runs expressions with.
     */
    public function __construct(ExpressionEvaluator $expressions, EvaluationBudget $budget)
    {
        $this->expressions = $expressions;
        $this->budget = $budget;
    }

    /**
     * Walks the statements, returning everything the body can return.
     *
     * @param array<array-key, Stmt> $statements
     */
    public function walk(array $statements, PathSet $paths, FunctionScope $scope): Domain
    {
        $returned = null;
        foreach ($statements as $statement) {
            if ($this->budget->isExhausted()) {
                break;
            }
            $value = $this->walkOne($statement, $paths, $scope);
            if ($value === null) {
                continue;
            }
            $returned = $returned === null ? $value : $returned->union($value);
        }

        return $returned ?? Domain::literal(null);
    }

    /**
     * Walks one statement, returning what it can return.
     */
    public function walkOne(Stmt $statement, PathSet $paths, FunctionScope $scope): ?Domain
    {
        if ($statement instanceof Stmt\Expression) {
            $this->evaluateEverywhere($statement->expr, $paths, $scope);

            return null;
        }
        if ($statement instanceof Stmt\Return_) {
            return $statement->expr === null
                ? Domain::literal(null)
                : $this->evaluateEverywhere($statement->expr, $paths, $scope);
        }
        if ($statement instanceof Stmt\If_) {
            return $this->walkConditional($statement, $paths, $scope);
        }
        if ($statement instanceof Stmt\Switch_) {
            return $this->walkSwitch($statement, $paths, $scope);
        }
        if ($this->isLoop($statement)) {
            return $this->walkLoop($statement, $paths, $scope);
        }
        if ($statement instanceof Stmt\TryCatch) {
            return $this->walkTry($statement, $paths, $scope);
        }

        return $this->walkOther($statement, $paths, $scope);
    }

    /**
     * Evaluates an expression on every path, returning what any of them can produce.
     */
    public function evaluateEverywhere(Expr $expression, PathSet $paths, FunctionScope $scope): Domain
    {
        $result = null;
        foreach ($paths->environments() as $environment) {
            $value = $this->expressions->evaluate($expression, $environment, $scope);
            $result = $result === null ? $value : $result->union($value);
        }

        return $result ?? Domain::unknown();
    }

    /**
     * Walks the statements that only need their expressions evaluated.
     */
    public function walkOther(Stmt $statement, PathSet $paths, FunctionScope $scope): ?Domain
    {
        if ($statement instanceof Stmt\Echo_) {
            foreach ($statement->exprs as $expression) {
                $this->evaluateEverywhere($expression, $paths, $scope);
            }

            return null;
        }
        if ($statement instanceof Stmt\Unset_) {
            foreach ($statement->vars as $variable) {
                $this->evaluateEverywhere($variable, $paths, $scope);
            }

            return null;
        }
        if ($statement instanceof Stmt\Block) {
            return $this->walk($statement->stmts, $paths, $scope);
        }
        if ($statement instanceof Stmt\Namespace_) {
            return $this->walk($statement->stmts, $paths, $scope);
        }
        if ($statement instanceof Stmt\Global_ || $statement instanceof Stmt\Static_) {
            $this->forgetDeclared($statement, $paths);
        }

        return null;
    }

    /**
     * Drops what was known about variables a declaration rebinds, on every path.
     */
    public function forgetDeclared(Stmt\Global_|Stmt\Static_ $statement, PathSet $paths): void
    {
        foreach ($statement->vars as $variable) {
            $name = $variable instanceof Node\StaticVar ? $variable->var : $variable;
            if (!$name instanceof Expr\Variable || !is_string($name->name)) {
                continue;
            }
            foreach ($paths->environments() as $environment) {
                $environment->forget($name->name);
            }
        }
    }

    /**
     * Whether the statement repeats its body.
     */
    public function isLoop(Stmt $statement): bool
    {
        return $statement instanceof Stmt\While_
            || $statement instanceof Stmt\Do_
            || $statement instanceof Stmt\For_
            || $statement instanceof Stmt\Foreach_;
    }

    /**
     * Forks the paths over every branch of a conditional.
     */
    public function walkConditional(Stmt\If_ $statement, PathSet $paths, FunctionScope $scope): ?Domain
    {
        $this->evaluateEverywhere($statement->cond, $paths, $scope);

        $branches = [$statement->stmts];
        foreach ($statement->elseifs as $elseif) {
            $this->evaluateEverywhere($elseif->cond, $paths, $scope);
            $branches[] = $elseif->stmts;
        }
        if ($statement->else !== null) {
            $branches[] = $statement->else->stmts;
        }

        return $this->walkBranches($branches, $paths, $scope, $statement->else === null);
    }

    /**
     * Forks the paths over every case of a switch.
     */
    public function walkSwitch(Stmt\Switch_ $statement, PathSet $paths, FunctionScope $scope): ?Domain
    {
        $this->evaluateEverywhere($statement->cond, $paths, $scope);

        $branches = [];
        $hasDefault = false;
        foreach ($statement->cases as $case) {
            $branches[] = $case->stmts;
            $hasDefault = $hasDefault || $case->cond === null;
        }

        return $this->walkBranches($branches, $paths, $scope, !$hasDefault);
    }

    /**
     * Walks alternative branches from the same paths and keeps their results apart.
     *
     * @param list<array<array-key, Stmt>> $branches
     * @param bool $mayFallThrough Whether control can reach the end without taking a branch
     */
    public function walkBranches(
        array $branches,
        PathSet $paths,
        FunctionScope $scope,
        bool $mayFallThrough,
    ): ?Domain {
        $reached = $mayFallThrough ? $paths->fork() : null;
        $returned = null;

        foreach ($branches as $branch) {
            $taken = $paths->fork();
            $value = $this->walk($branch, $taken, $scope);
            $reached = $reached === null ? $taken : $reached->merge($taken);
            $returned = $returned === null ? $value : $returned->union($value);
        }

        $paths->becomeFrom(($reached ?? $paths->fork())->bounded());

        return $returned;
    }

    /**
     * Walks a loop body twice per path and widens whatever kept changing.
     */
    public function walkLoop(Stmt $statement, PathSet $paths, FunctionScope $scope): Domain
    {
        $body = $this->loopBody($statement, $paths, $scope);
        $before = $paths->fork();

        $first = $paths->fork();
        $returned = $this->walk($body, $first, $scope);

        $second = $first->fork();
        for ($pass = 1; $pass < $this->budget->maxLoopPasses; $pass++) {
            $this->walk($body, $second, $scope);
        }

        $paths->becomeFrom($before->merge($this->widenPaths($first, $second)));

        return $returned;
    }

    /**
     * The body of a loop, after binding whatever the loop iterates over.
     *
     * @return array<array-key, Stmt>
     */
    public function loopBody(Stmt $statement, PathSet $paths, FunctionScope $scope): array
    {
        if ($statement instanceof Stmt\Foreach_) {
            $this->bindIteration($statement, $paths, $scope);

            return $statement->stmts;
        }
        if ($statement instanceof Stmt\While_ || $statement instanceof Stmt\Do_) {
            $this->evaluateEverywhere($statement->cond, $paths, $scope);

            return $statement->stmts;
        }
        if ($statement instanceof Stmt\For_) {
            foreach ($statement->init as $expression) {
                $this->evaluateEverywhere($expression, $paths, $scope);
            }

            return $statement->stmts;
        }

        return [];
    }

    /**
     * Binds the key and value variables of a `foreach` on every path.
     */
    public function bindIteration(Stmt\Foreach_ $statement, PathSet $paths, FunctionScope $scope): void
    {
        foreach ($paths->environments() as $environment) {
            $subject = $this->expressions->evaluate($statement->expr, $environment, $scope);
            $array = $subject->soleArray();
            $element = $array === null
                ? Domain::opaque(TypeShape::unknown(), Origin::Loop, 'iterated value')
                : $this->elementsOf($array);

            if ($statement->keyVar instanceof Expr) {
                $this->expressions->evaluate($statement->keyVar, $environment, $scope);
            }
            if ($statement->valueVar instanceof Expr\Variable && is_string($statement->valueVar->name)) {
                $environment->write($statement->valueVar->name, $element);
            }
        }
    }

    /**
     * Everything an array can yield when it is iterated.
     */
    public function elementsOf(ArrayTerm $array): Domain
    {
        $element = null;
        foreach ($array->entries as $entry) {
            $element = $element === null ? $entry->value : $element->union($entry->value);
        }

        return $element ?? Domain::opaque(TypeShape::unknown(), Origin::Loop, 'iterated value');
    }

    /**
     * The paths covering both passes of a loop, widening whatever kept changing.
     */
    public function widenPaths(PathSet $first, PathSet $second): PathSet
    {
        if ($first->count() !== $second->count()) {
            return PathSet::of($this->widen($first->join(), $second->join()));
        }

        $widened = [];
        foreach ($first->environments() as $index => $environment) {
            $widened[] = $this->widen($environment, $second->environments()[$index]);
        }

        return new PathSet($widened, $first->isJoined() || $second->isJoined());
    }

    /**
     * The environment covering both passes, widening whatever kept changing.
     */
    public function widen(Environment $first, Environment $second): Environment
    {
        $widened = $first->copy();
        foreach (array_unique(array_merge($first->names(), $second->names())) as $name) {
            $left = $first->read($name);
            $right = $second->read($name);
            $widened->write($name, $left->equals($right) ? $left : $left->union($right)->collapse(Origin::Loop));
        }

        return $widened;
    }

    /**
     * Walks a try block together with the handlers that can follow it.
     */
    public function walkTry(Stmt\TryCatch $statement, PathSet $paths, FunctionScope $scope): Domain
    {
        $returned = $this->walk($statement->stmts, $paths, $scope);
        $branches = [];
        foreach ($statement->catches as $catch) {
            $branches[] = $catch->stmts;
        }
        $caught = $this->walkBranches($branches, $paths, $scope, true);
        if ($statement->finally !== null) {
            $this->walk($statement->finally->stmts, $paths, $scope);
        }

        return $caught === null ? $returned : $returned->union($caught);
    }
}
