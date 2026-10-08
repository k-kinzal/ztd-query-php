<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Error\QueryError;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Operator\Comparison\RowCompare;
use MySqlMemory\Evaluation\Operator\Logic;
use MySqlMemory\Evaluation\Scope;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
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
     * Compiles a comparison of two rows.
     *
     * @throws \MySqlMemory\Error\SqlError When the rows have different widths
     */
    public function compare(ComparisonOperator $operator, Scalar $left, Scalar $right, Scope $scope): Evaluable
    {
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
