<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Evaluation\Subquery\Existence;
use MySqlMemory\Evaluation\Subquery\Quantified;
use MySqlMemory\Evaluation\Subquery\Rows;
use MySqlMemory\Evaluation\Subquery\ScalarRead;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;

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
     * Compiles a scalar subquery; one that depends on no row, assigns no variable and is not read as the expression of its one row (substituted()) runs once for the statement.
     *
     * @throws \MySqlMemory\Error\SqlError When the subquery has more than one column
     */
    public function scalar(ScalarSubquery $node, Scope $scope): Evaluable
    {
        $plan = $this->compiler->planner->query($node->query, $scope);
        if (count($plan->domains) !== 1) {
            throw QueryError::OperandColumns->error(1);
        }

        $once = Constancy::of($node->query, $this->compiler->facts) !== Constancy::Row && !$this->substituted($node->query) && (new Walker())->find($node->query, VariableAssignment::class) === [];

        return new ScalarRead(new Rows($plan), $this->compiler->resolved($node) ?? $plan->domains[0]->withNullable(true), $once);
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
        return $this->quantified($node->operand, ComparisonOperator::Equal, false, $node->negated, $node->query, $scope, $node);
    }

    /**
     * Compiles a comparison with ANY, SOME or ALL.
     *
     * @throws \MySqlMemory\Error\SqlError When the subquery has more than one column
     */
    public function quantifiedComparison(QuantifiedComparison $node, Scope $scope): Evaluable
    {
        return $this->quantified($node->operand, $node->operator, $node->quantifier === Quantifier::All, false, $node->query, $scope, $node);
    }

    /**
     * Compiles a quantified comparison of a value with the rows of a subquery.
     *
     * With `=` or `<>` over a subquery that is one row computed without a table, the server
     * compares the value with the expression of the row as a plain comparison does; NOT IN
     * compares with `<>`.
     *
     * @throws \MySqlMemory\Error\SqlError When the subquery has more than one column
     */
    public function quantified(\SqlSemantics\Statement\Scalar $operand, ComparisonOperator $operator, bool $all, bool $negated, \SqlSemantics\Statement\Query $query, Scope $scope, \SqlSemantics\Statement\Scalar $node): Evaluable
    {
        $value = $this->compiler->compile($operand, $scope);
        $plan = $this->compiler->planner->query($query, $scope);
        if (count($plan->domains) !== 1) {
            throw QueryError::OperandColumns->error(1);
        }
        $single = $operator === ComparisonOperator::Equal || $operator === ComparisonOperator::NotEqual ? $this->single($query) : null;
        if ($single !== null) {
            return $this->compiler->operators->compare($negated ? ComparisonOperator::NotEqual : $operator, $operand, $single, $scope, $node, $value);
        }
        $comparator = Comparator::of($value->domain(), $plan->domains[0], $operator->value, $this->compiler->settings->connectionCollation);

        return new Quantified($value, new Rows($plan), $operator, $all, $negated, $comparator, $this->compiler->operators->truth(true));
    }

    /**
     * Tells whether the server reads a scalar subquery as the expression of its one row: a SELECT of one expression without a table, an aggregate, HAVING or a window.
     */
    public function substituted(\SqlSemantics\Statement\Query $query): bool
    {
        while ($query instanceof ParenthesizedQuery || ($query instanceof QueryExpression && $query->with === null)) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        if (!$query instanceof Select || ($query->from !== null && !$query->from instanceof Dual) || $query->having !== null || $query->windows !== [] || count($query->items) !== 1) {
            return false;
        }
        $item = $query->items[0];

        return $item instanceof SelectExpression && !$this->aggregated($item->expression);
    }

    /**
     * Answers the expression of a subquery that is one row computed without a table, or null for any other subquery.
     *
     * It is a VALUES of one row, or a SELECT of one expression without a table, an aggregate,
     * a WHERE or HAVING clause, a window or a LIMIT.
     */
    public function single(\SqlSemantics\Statement\Query $query): ?\SqlSemantics\Statement\Scalar
    {
        $query = $this->innermost($query);
        if ($query instanceof ValuesQuery) {
            return count($query->rows) === 1 && count($query->rows[0]->values) === 1 ? $query->rows[0]->values[0] : null;
        }

        return $query instanceof Select ? $this->selected($query) : null;
    }

    /**
     * Answers the query a subquery evaluates once the parentheses and the query expressions without a WITH or LIMIT clause around it are removed.
     */
    public function innermost(\SqlSemantics\Statement\Query $query): \SqlSemantics\Statement\Query
    {
        while ($query instanceof ParenthesizedQuery || ($query instanceof QueryExpression && $query->with === null && $query->limit === null)) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }

        return $query;
    }

    /**
     * Answers the expression of a SELECT of one expression without a table, an aggregate, a WHERE or HAVING clause, a window or a LIMIT, or null for any other SELECT.
     */
    public function selected(Select $query): ?\SqlSemantics\Statement\Scalar
    {
        if (($query->from !== null && !$query->from instanceof Dual) || $query->where !== null || $query->having !== null || $query->windows !== [] || $query->qualify !== null || $query->limit !== null || $query->into !== null || count($query->items) !== 1) {
            return null;
        }
        $item = $query->items[0];

        return $item instanceof SelectExpression && !$this->aggregated($item->expression) ? $item->expression : null;
    }

    /**
     * Tells whether an expression holds an aggregate of its own block: a set function, GROUP_CONCAT or JSON_OBJECTAGG.
     */
    public function aggregated(\SqlSemantics\Statement\Scalar $expression): bool
    {
        return (new Walker())->find($expression, Aggregate::class, false) !== [] || (new Walker())->find($expression, GroupConcat::class, false) !== [] || (new Walker())->find($expression, JsonObjectAggregate::class, false) !== [];
    }
}
