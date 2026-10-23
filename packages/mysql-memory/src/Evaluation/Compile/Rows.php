<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Operator\Comparison\RowCompare;
use MySqlMemory\Evaluation\Operator\Logic;
use MySqlMemory\Evaluation\Scope;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\RowSubquery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
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
     * @return list<Scalar|RowOperand>|null
     */
    public function elements(Scalar|RowOperand $node): ?array
    {
        while ($node instanceof Grouped || $node instanceof GroupedRow) {
            $node = $node->operand;
        }

        return $node instanceof Row ? $node->elements : null;
    }

    /**
     * Tells whether the operand is a written row or resolves to a multi-column subquery.
     *
     * @phpstan-assert-if-false Scalar $node
     */
    public function tuple(Scalar|RowOperand $node): bool
    {
        if ($node instanceof RowOperand || $this->elements($node) !== null) {
            return true;
        }
        $type = $this->compiler->facts->covers($node) ? $this->compiler->facts->scalar($node)->type : null;

        return $type instanceof \SqlSemantics\Statement\Type\Known && $type->descriptor instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Tuple;
    }

    /**
     * Finds a row subquery through grouping parentheses.
     */
    public function subquery(Scalar|RowOperand $node): ScalarSubquery|RowSubquery|null
    {
        while ($node instanceof Grouped || $node instanceof GroupedRow) {
            $node = $node->operand;
        }

        return $node instanceof ScalarSubquery || $node instanceof RowSubquery ? $node : null;
    }

    /**
     * Compiles a comparison of two rows.
     *
     * @throws \MySqlMemory\Error\SqlError When the rows have different widths
     */
    public function compare(ComparisonOperator $operator, Scalar|RowOperand $left, Scalar|RowOperand $right, Scope $scope): Evaluable
    {
        if ($this->subquery($left) !== null || $this->subquery($right) !== null) {
            return $this->subqueried($operator, $left, $right, $scope);
        }
        $pairs = [];
        $nullable = false;
        foreach ($this->paired($left, $right) as [$one, $two]) {
            $first = $this->compiler->compile($one, $scope);
            $second = $this->compiler->compile($two, $scope);
            $pairs[] = [$first, $second, Comparator::of($first->domain(), $second->domain(), $operator->value, $this->compiler->settings->connectionCollation, $this->compiler->settings->release())];
            $nullable = $nullable || $first->domain()->nullable || $second->domain()->nullable;
        }

        return new RowCompare($operator, $pairs, $this->compiler->operators->truth($nullable && $operator !== ComparisonOperator::NullSafeEqual));
    }

    /**
     * Pairs corresponding leaves while retaining the arity of every nested row.
     * Depth-first order preserves row comparison short-circuiting and NULL behavior.
     *
     * @return list<array{Scalar, Scalar}>
     * @throws \MySqlMemory\Error\SqlError When corresponding rows have different shapes
     */
    public function paired(Scalar|RowOperand $left, Scalar|RowOperand $right): array
    {
        $one = $this->elements($left);
        $two = $this->elements($right);
        if ($one === null && $two === null) {
            assert($left instanceof Scalar && $right instanceof Scalar);
            return [[$left, $right]];
        }
        $one ??= [$left];
        $two ??= [$right];
        if (count($one) !== count($two)) {
            throw QueryError::OperandColumns->error(count($one));
        }
        $pairs = [];
        foreach ($one as $index => $first) {
            foreach ($this->paired($first, $two[$index]) as $pair) {
                $pairs[] = $pair;
            }
        }

        return $pairs;
    }

    /**
     * Compiles a row constructor or a single-row subquery.
     *
     * @return list<Evaluable>|\MySqlMemory\Evaluation\Subquery\SingleRow
     */
    public function operand(Scalar|RowOperand $node, Scope $scope): array|\MySqlMemory\Evaluation\Subquery\SingleRow
    {
        $query = $this->subquery($node);

        return $query !== null ? $this->compiler->subqueries->row($query, $scope) : array_map(fn (Scalar|RowOperand $element): Evaluable => $this->compiler->compile($element, $scope), $this->elements($node) ?? [$node]);
    }

    /**
     * Compiles a comparison involving a row subquery using each output column's resolved domain.
     *
     * @throws \MySqlMemory\Error\SqlError When the operands have different column counts
     */
    public function subqueried(ComparisonOperator $operator, Scalar|RowOperand $left, Scalar|RowOperand $right, Scope $scope): Evaluable
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
     * @param list<Scalar|RowOperand> $elements
     */
    public function in(Scalar|RowOperand $operand, array $elements, bool $negated, Scope $scope): Evaluable
    {
        $result = null;
        $json = false;
        foreach ($elements as $element) {
            $equality = $this->compare(ComparisonOperator::Equal, $operand, $element, $scope);
            if (count($elements) > 1 && $equality instanceof RowCompare) {
                $json = $json || array_filter($equality->pairs, static fn (array $pair): bool => $pair[0]->domain()->kind === Kind::Json || $pair[1]->domain()->kind === Kind::Json) !== [];
                $pairs = array_map(static fn (array $pair): array => [$pair[0], $pair[1], $pair[2]->mode === Kind::Json ? new Comparator(Kind::String, $pair[0]->domain(), $pair[1]->domain(), Collation::known('utf8mb4_bin')) : $pair[2]], $equality->pairs);
                $equality = new RowCompare(ComparisonOperator::Equal, $pairs, $equality->domain());
            }
            $result = $result === null ? $equality : new Logic(LogicalOperator::Or, $result, $equality, $this->compiler->operators->truth($result->domain()->nullable || $equality->domain()->nullable));
        }
        if ($json && !$this->compiler->settings->legacy()) {
            $this->compiler->connection->context->diagnostics->warning(StatementError::NotSupportedYet, StatementError::NotSupportedYet->message('comparison of JSON within a ROW in the IN operator'));
        }
        assert($result !== null);

        return $negated ? new \MySqlMemory\Evaluation\Operator\Negation($result, $result->domain()) : $result;
    }
}
