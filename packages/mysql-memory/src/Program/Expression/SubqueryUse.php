<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Scope;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate;
use SqlSemantics\Statement\Scalar;

/**
 * Prepares a retained subquery predicate in the release's dependency order.
 *
 * @visibility MySqlMemory
 */
final class SubqueryUse
{
    /**
     * Prepares the query and operand before the fixed condition that this form requires.
     *
     * @throws \MySqlMemory\Error\SqlError Always, after the earlier dependency checks
     */
    public function raise(DeferredSubqueryPredicate $predicate, Compiler $compiler, Scope $scope): never
    {
        $operandFirst = $compiler->settings->release() === GrammarRelease::MySql8044;
        if ($operandFirst) {
            $this->operand($predicate, $compiler, $scope);
        }
        $plan = $compiler->planner->query($predicate->query, $scope);
        if (!$compiler->settings->legacy() && !$predicate->rows() && count($plan->domains) !== 1) {
            throw QueryError::OperandColumns->error(1);
        }
        if (!$operandFirst) {
            $this->operand($predicate, $compiler, $scope);
        }
        throw QueryError::OperandColumns->error($predicate->problem()->expected);
    }

    /**
     * Binds the outer operand in the diagnostic scope of subquery comparisons.
     */
    public function operand(DeferredSubqueryPredicate $predicate, Compiler $compiler, Scope $scope): void
    {
        $scope = $scope->inClause('IN/ALL/ANY subquery');
        if ($predicate->operand instanceof Scalar) {
            $compiler->compile($predicate->operand, $scope);
        } else {
            (new RowUse())->prepare($predicate->operand, $compiler, $scope);
        }
    }
}
