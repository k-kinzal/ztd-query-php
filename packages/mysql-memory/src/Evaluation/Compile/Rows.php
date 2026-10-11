<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Operator\Comparison\RowCompare;
use MySqlMemory\Evaluation\Operator\Logic;
use MySqlMemory\Evaluation\Scope;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Statement\Scalar;

/**
 * Compiles comparisons of row constructors, and IN lists of rows.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Rows
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Answers the elements of a row constructor, or null for a single value.
     *
     * @return list<Scalar>|null
     */
    public function elements(Scalar $node): ?array
    {
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }

        return $node instanceof Row ? $node->elements : null;
    }

    /**
     * Tells whether the operand is a written row or resolves to a multi-column subquery.
     */
    public function tuple(Scalar $node): bool
    {
        if ($this->elements($node) !== null) {
            return true;
        }
        $type = $this->compiler->facts->covers($node) ? $this->compiler->facts->scalar($node)->type : null;

        return $type instanceof \SqlSemantics\Statement\Type\Known && $type->descriptor instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Tuple;
    }

    /**
     * Finds a row subquery through grouping parentheses.
     */
    public function subquery(Scalar $node): ?ScalarSubquery
    {
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }

        return $node instanceof ScalarSubquery ? $node : null;
    }

    /**
     * Compiles a comparison of two rows.
     *
     * @throws \MySqlMemory\Error\SqlError When the rows have different widths
     */
    public function compare(ComparisonOperator $operator, Scalar $left, Scalar $right, Scope $scope): Evaluable
    {
        if ($this->subquery($left) !== null || $this->subquery($right) !== null) {
            return $this->subqueried($operator, $left, $right, $scope);
        }
        $one = $this->elements($left) ?? [$left];
        $two = $this->elements($right) ?? [$right];
        if (count($one) !== count($two)) {
            throw QueryError::OperandColumns->error(count($one));
        }
        $pairs = [];
        $nullable = false;
        foreach ($one as $index => $element) {
            $first = $this->compiler->compile($element, $scope);
            $second = $this->compiler->compile($two[$index], $scope);
            $pairs[] = [$first, $second, Comparator::of($first->domain(), $second->domain(), $operator->value, $this->compiler->settings->connectionCollation, $this->compiler->settings->release())];
            $nullable = $nullable || $first->domain()->nullable || $second->domain()->nullable;
        }

        return new RowCompare($operator, $pairs, $this->compiler->operators->truth($nullable && $operator !== ComparisonOperator::NullSafeEqual));
    }

    /**
     * Compiles a row constructor or a single-row subquery.
     *
     * @return list<Evaluable>|\MySqlMemory\Evaluation\Subquery\SingleRow
     */
    public function operand(Scalar $node, Scope $scope): array|\MySqlMemory\Evaluation\Subquery\SingleRow
    {
        $query = $this->subquery($node);

        return $query !== null ? $this->compiler->subqueries->row($query, $scope) : array_map(fn (Scalar $element): Evaluable => $this->compiler->compile($element, $scope), $this->elements($node) ?? [$node]);
    }

    /**
     * Compiles a comparison involving a row subquery using each output column's resolved domain.
     *
     * @throws \MySqlMemory\Error\SqlError When the operands have different column counts
     */
    public function subqueried(ComparisonOperator $operator, Scalar $left, Scalar $right, Scope $scope): Evaluable
    {
        $one = $this->operand($left, $scope);
        $two = $this->operand($right, $scope);
        $first = $one instanceof \MySqlMemory\Evaluation\Subquery\SingleRow ? $one->domains() : array_map(static fn (Evaluable $value): \MySqlMemory\Typing\Domain => $value->domain(), $one);
        $second = $two instanceof \MySqlMemory\Evaluation\Subquery\SingleRow ? $two->domains() : array_map(static fn (Evaluable $value): \MySqlMemory\Typing\Domain => $value->domain(), $two);
        if (count($first) !== count($second)) {
            throw QueryError::OperandColumns->error(count($first));
        }
        $comparators = [];
        foreach ($first as $index => $domain) {
            $comparators[] = Comparator::of($domain, $second[$index], $operator->value, $this->compiler->settings->connectionCollation, $this->compiler->settings->release());
        }

        return new \MySqlMemory\Evaluation\Subquery\RowComparison($operator, $one, $two, $comparators, $this->compiler->operators->truth($operator !== ComparisonOperator::NullSafeEqual), $this->compiler->settings->legacy());
    }

    /**
     * Compiles `row [NOT] IN (row, ...)` as a disjunction of row equalities.
     *
     * @param list<Scalar> $elements
     */
    public function in(Scalar $operand, array $elements, bool $negated, Scope $scope): Evaluable
    {
        $result = null;
        foreach ($elements as $element) {
            $equality = $this->compare(ComparisonOperator::Equal, $operand, $element, $scope);
            $result = $result === null ? $equality : new Logic(LogicalOperator::Or, $result, $equality, $this->compiler->operators->truth($result->domain()->nullable || $equality->domain()->nullable));
        }
        assert($result !== null);

        return $negated ? new \MySqlMemory\Evaluation\Operator\Negation($result, $result->domain()) : $result;
    }
}
