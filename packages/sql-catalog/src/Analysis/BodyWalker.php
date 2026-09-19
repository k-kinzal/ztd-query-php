<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Text\Origin;
use SqlCatalog\Type\TypeShape;

/**
 * Walks a function body, keeping what each variable can hold at each point.
 *
 * Branches are joined rather than picked between, so a statement assembled in
 * an `if` is reported as both of the statements it can be. A loop is walked
 * twice and then widened, which turns the familiar `$sql .= ' AND …'` pattern
 * into one shape covering every number of iterations.
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
    public function walk(array $statements, Environment $environment, FunctionScope $scope): Domain
    {
        $returned = null;
        foreach ($statements as $statement) {
            if ($this->budget->isExhausted()) {
                break;
            }
            $value = $this->walkOne($statement, $environment, $scope);
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
    public function walkOne(Stmt $statement, Environment $environment, FunctionScope $scope): ?Domain
    {
        if ($statement instanceof Stmt\Expression) {
            $this->expressions->evaluate($statement->expr, $environment, $scope);

            return null;
        }
        if ($statement instanceof Stmt\Return_) {
            return $statement->expr === null
                ? Domain::literal(null)
                : $this->expressions->evaluate($statement->expr, $environment, $scope);
        }
        if ($statement instanceof Stmt\If_) {
            return $this->walkConditional($statement, $environment, $scope);
        }
        if ($statement instanceof Stmt\Switch_) {
            return $this->walkSwitch($statement, $environment, $scope);
        }
        if ($this->isLoop($statement)) {
            return $this->walkLoop($statement, $environment, $scope);
        }
        if ($statement instanceof Stmt\TryCatch) {
            return $this->walkTry($statement, $environment, $scope);
        }

        return $this->walkOther($statement, $environment, $scope);
    }

    /**
     * Walks the statements that only need their expressions evaluated.
     */
    public function walkOther(Stmt $statement, Environment $environment, FunctionScope $scope): ?Domain
    {
        if ($statement instanceof Stmt\Echo_) {
            foreach ($statement->exprs as $expression) {
                $this->expressions->evaluate($expression, $environment, $scope);
            }

            return null;
        }
        if ($statement instanceof Stmt\Unset_) {
            foreach ($statement->vars as $variable) {
                $this->expressions->evaluate($variable, $environment, $scope);
            }

            return null;
        }
        if ($statement instanceof Stmt\Block) {
            return $this->walk($statement->stmts, $environment, $scope);
        }
        if ($statement instanceof Stmt\Namespace_) {
            return $this->walk($statement->stmts, $environment, $scope);
        }
        if ($statement instanceof Stmt\Global_ || $statement instanceof Stmt\Static_) {
            $this->forgetDeclared($statement, $environment);
        }

        return null;
    }

    /**
     * Drops what was known about variables a declaration rebinds.
     */
    public function forgetDeclared(Stmt\Global_|Stmt\Static_ $statement, Environment $environment): void
    {
        foreach ($statement->vars as $variable) {
            $name = $variable instanceof Node\StaticVar ? $variable->var : $variable;
            if ($name instanceof Node\Expr\Variable && is_string($name->name)) {
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
     * Walks every branch of a conditional and joins what they leave behind.
     */
    public function walkConditional(Stmt\If_ $statement, Environment $environment, FunctionScope $scope): ?Domain
    {
        $this->expressions->evaluate($statement->cond, $environment, $scope);

        $branches = [$statement->stmts];
        foreach ($statement->elseifs as $elseif) {
            $this->expressions->evaluate($elseif->cond, $environment, $scope);
            $branches[] = $elseif->stmts;
        }
        if ($statement->else !== null) {
            $branches[] = $statement->else->stmts;
        }

        return $this->walkBranches($branches, $environment, $scope, $statement->else === null);
    }

    /**
     * Walks every case of a switch and joins what they leave behind.
     */
    public function walkSwitch(Stmt\Switch_ $statement, Environment $environment, FunctionScope $scope): ?Domain
    {
        $this->expressions->evaluate($statement->cond, $environment, $scope);

        $branches = [];
        $hasDefault = false;
        foreach ($statement->cases as $case) {
            $branches[] = $case->stmts;
            $hasDefault = $hasDefault || $case->cond === null;
        }

        return $this->walkBranches($branches, $environment, $scope, !$hasDefault);
    }

    /**
     * Walks alternative branches from the same starting point and joins the results.
     *
     * @param list<array<array-key, Stmt>> $branches
     * @param bool $mayFallThrough Whether control can reach the end without taking a branch
     */
    public function walkBranches(
        array $branches,
        Environment $environment,
        FunctionScope $scope,
        bool $mayFallThrough,
    ): ?Domain {
        $joined = $mayFallThrough ? $environment->copy() : null;
        $returned = null;

        foreach ($branches as $branch) {
            $branchEnvironment = $environment->copy();
            $value = $this->walk($branch, $branchEnvironment, $scope);
            $joined = $joined === null ? $branchEnvironment : $joined->join($branchEnvironment);
            $returned = $returned === null ? $value : $returned->union($value);
        }

        $this->adopt($environment, $joined ?? $environment->copy());

        return $returned;
    }

    /**
     * Walks a loop body twice and widens whatever kept changing.
     */
    public function walkLoop(Stmt $statement, Environment $environment, FunctionScope $scope): Domain
    {
        $body = $this->loopBody($statement, $environment, $scope);
        $before = $environment->copy();

        $first = $environment->copy();
        $returned = $this->walk($body, $first, $scope);

        $second = $first->copy();
        for ($pass = 1; $pass < $this->budget->maxLoopPasses; $pass++) {
            $this->walk($body, $second, $scope);
        }

        $this->adopt($environment, $before->join($this->widen($first, $second)));

        return $returned;
    }

    /**
     * The body of a loop, after binding whatever the loop iterates over.
     *
     * @return array<array-key, Stmt>
     */
    public function loopBody(Stmt $statement, Environment $environment, FunctionScope $scope): array
    {
        if ($statement instanceof Stmt\Foreach_) {
            $this->bindIteration($statement, $environment, $scope);

            return $statement->stmts;
        }
        if ($statement instanceof Stmt\While_ || $statement instanceof Stmt\Do_) {
            $this->expressions->evaluate($statement->cond, $environment, $scope);

            return $statement->stmts;
        }
        if ($statement instanceof Stmt\For_) {
            foreach ($statement->init as $expression) {
                $this->expressions->evaluate($expression, $environment, $scope);
            }

            return $statement->stmts;
        }

        return [];
    }

    /**
     * Binds the key and value variables of a `foreach` to what the subject holds.
     */
    public function bindIteration(Stmt\Foreach_ $statement, Environment $environment, FunctionScope $scope): void
    {
        $subject = $this->expressions->evaluate($statement->expr, $environment, $scope);
        $array = $subject->soleArray();
        $element = $array === null
            ? Domain::opaque(TypeShape::unknown(), Origin::Loop, 'iterated value')
            : $this->elementsOf($array);

        if ($statement->keyVar instanceof Node\Expr) {
            $this->expressions->evaluate($statement->keyVar, $environment, $scope);
        }
        if ($statement->valueVar instanceof Node\Expr\Variable && is_string($statement->valueVar->name)) {
            $environment->write($statement->valueVar->name, $element);
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
     * Replaces what the environment knows with what the joined one knows.
     */
    public function adopt(Environment $environment, Environment $joined): void
    {
        foreach ($environment->names() as $name) {
            $environment->forget($name);
        }
        foreach ($joined->names() as $name) {
            $environment->write($name, $joined->read($name));
        }
    }

    /**
     * Walks a try block together with the handlers that can follow it.
     */
    public function walkTry(Stmt\TryCatch $statement, Environment $environment, FunctionScope $scope): Domain
    {
        $returned = $this->walk($statement->stmts, $environment, $scope);
        $branches = [];
        foreach ($statement->catches as $catch) {
            $branches[] = $catch->stmts;
        }
        $caught = $this->walkBranches($branches, $environment, $scope, true);
        if ($statement->finally !== null) {
            $this->walk($statement->finally->stmts, $environment, $scope);
        }

        return $caught === null ? $returned : $returned->union($caught);
    }
}
