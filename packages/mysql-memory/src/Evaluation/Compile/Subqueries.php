<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Evaluation\Subquery\Existence;
use MySqlMemory\Evaluation\Subquery\Quantified;
use MySqlMemory\Evaluation\Subquery\Rows;
use MySqlMemory\Evaluation\Subquery\ScalarRead;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;

/**
 * Compiles subqueries used as values and in predicates; each is planned in a scope inside the block that reads it.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Subqueries
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Compiles a scalar subquery.
     *
     * @throws \MySqlMemory\Error\SqlError When the subquery has more than one column
     */
    public function scalar(ScalarSubquery $node, Scope $scope): Evaluable
    {
        $plan = $this->compiler->planner->query($node->query, $scope);
        if (count($plan->domains) !== 1) {
            throw ErrorCode::OperandColumns->error(1);
        }

        return new ScalarRead(new Rows($plan), $this->compiler->resolved($node) ?? $plan->domains[0]->withNullable(true));
    }

    /**
     * Compiles EXISTS.
     */
    public function exists(Exists $node, Scope $scope): Evaluable
    {
        return new Existence(new Rows($this->compiler->planner->query($node->query, $scope)), $this->compiler->domain($node));
    }

    /**
     * Compiles [NOT] IN with a subquery.
     *
     * @throws \MySqlMemory\Error\SqlError When the subquery has more than one column
     */
    public function in(InQuery $node, Scope $scope): Evaluable
    {
        return $this->quantified($node->operand, ComparisonOperator::Equal, false, $node->negated, $node->query, $scope);
    }

    /**
     * Compiles a comparison with ANY, SOME or ALL.
     *
     * @throws \MySqlMemory\Error\SqlError When the subquery has more than one column
     */
    public function quantifiedComparison(QuantifiedComparison $node, Scope $scope): Evaluable
    {
        return $this->quantified($node->operand, $node->operator, $node->quantifier === Quantifier::All, false, $node->query, $scope);
    }

    /**
     * Compiles a quantified comparison of a value with the rows of a subquery.
     *
     * @throws \MySqlMemory\Error\SqlError When the subquery has more than one column
     */
    public function quantified(\SqlSemantics\Statement\Scalar $operand, ComparisonOperator $operator, bool $all, bool $negated, \SqlSemantics\Statement\Query $query, Scope $scope): Evaluable
    {
        $value = $this->compiler->compile($operand, $scope);
        $plan = $this->compiler->planner->query($query, $scope);
        if (count($plan->domains) !== 1) {
            throw ErrorCode::OperandColumns->error(1);
        }
        $comparator = Comparator::of($value->domain(), $plan->domains[0], $operator->value, $this->compiler->settings->connectionCollation);

        return new Quantified($value, new Rows($plan), $operator, $all, $negated, $comparator, $this->compiler->operators->truth(true));
    }
}
