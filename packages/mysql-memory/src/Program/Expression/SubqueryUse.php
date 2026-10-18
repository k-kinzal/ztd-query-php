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
        $operandFirst = $compiler->settings->release() === GrammarRelease::MySql8044 || ($compiler->connection->viewDefinition && $compiler->settings->legacy());
        $deferSetWidth = !$compiler->connection->viewDefinition && ($compiler->settings->legacy() || $operandFirst);
        if ($operandFirst) {
            $this->operand($predicate, $compiler, $scope);
        }
        $constraint = (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns())->problem($predicate->query);
        if ($constraint !== null) {
            (new QueryUse())->prepare($predicate->query, $compiler, deferSetWidth: $deferSetWidth);
            if (!$operandFirst) {
                $this->operand($predicate, $compiler, $scope);
            }
            if ($deferSetWidth && ($predicate->rows() || $operandFirst) && $constraint instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch && $constraint->list === \SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList::SetOperands) {
                \SqlSemantics\Diagnostic\Check::invariant(!$predicate->operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand, 'The compared operand has prepared without a deferred condition.');
                throw QueryError::OperandColumns->error(max(1, count((new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes())->parts($predicate->operand) ?? [])));
            }
            throw (new RowUse())->error($predicate);
        }
        $plan = $compiler->planner->query($predicate->query, $scope);
        if (!$compiler->settings->legacy() && !$predicate->rows() && count($plan->domains) !== 1) {
            throw QueryError::OperandColumns->error(1);
        }
        if (!$operandFirst) {
            $this->operand($predicate, $compiler, $scope);
        }
        throw (new RowUse())->error($predicate);
    }

    /**
     * Binds the outer operand in the diagnostic scope of subquery comparisons.
     */
    public function operand(DeferredSubqueryPredicate $predicate, Compiler $compiler, Scope $scope): void
    {
        $scope = $scope->inClause($compiler->connection->viewDefinition && $compiler->settings->legacy() ? 'field list' : 'IN/ALL/ANY subquery');
        if ($predicate->operand instanceof Scalar) {
            $compiler->compile($predicate->operand, $scope);
        } else {
            (new RowUse())->prepare($predicate->operand, $compiler, $scope);
        }
    }
}
