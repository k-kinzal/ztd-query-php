<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\QueryWidth;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers operands into their scalar or row role before constructing expressions.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class OperandRule
{
    /**
     * Refuses a row in a position requiring one value.
     *
     * @throws RejectedInput When the input supplies a row
     */
    public function scalar(Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $operand, Node $source): Scalar
    {
        if ($operand instanceof Scalar) {
            return $operand;
        }
        throw new RejectedInput($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand ? $operand->problem() : new OperandColumns(1, count((new RowShapes())->parts($operand) ?? [])), (new SourceRanges())->span($source)[1] ?? 0, false);
    }

    /**
     * Preserves the enclosed operand's role through parentheses.
     */
    public function grouped(Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $operand): Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand
    {
        return $operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand ? new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped($operand) : ($operand instanceof Scalar ? new Grouped($operand) : new GroupedRow($operand));
    }

    /**
     * Resolves an explicit collation before refusing a row in its scalar position.
     *
     * @throws RejectedInput When the collation is unknown or its operand is a row
     */
    public function collated(Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $operand, \SqlSemantics\Statement\Identifier\Name $collation, Node $source): \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated
    {
        if ($operand instanceof RowOperand && $collation->value !== '' && \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::named($collation->value) === null) {
            throw new RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation($collation->value), (new SourceRanges())->span($source)[1] ?? 0, false);
        }
        return new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated($this->scalar($operand, $source), $collation);
    }

    /**
     * Distinguishes fixed multi-column projections from scalar or catalog-dependent output.
     */
    public function subquery(Query $query): Subquery\ScalarSubquery|Subquery\RowSubquery
    {
        return (new QueryWidth())->of($query) > 1 ? new Subquery\RowSubquery($query) : new Subquery\ScalarSubquery($query);
    }

    /**
     * Constructs a comparison after checking every statically known row field.
     *
     * @throws RejectedInput When the operands contradict one another
     */
    public function comparison(ComparisonOperator $operator, Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $left, Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $right, Node $source): Comparison
    {
        $left = $this->row($left, $source);
        $right = $this->row($right, $source);
        $this->check((new RowShapes())->problem($left, $right), $source);
        return new Comparison($operator, $left, $right);
    }

    /**
     * Constructs list membership with compatible element shapes.
     *
     * @param list<Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $elements
     * @throws RejectedInput When an element has an incompatible shape
     */
    public function inList(Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $operand, array $elements, bool $negated, Node $source): InList|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredMembership
    {
        if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand) {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredMembership($operand, $elements, $negated);
        }
        foreach ($elements as $element) {
            if ($element instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand || (new RowShapes())->problem($operand, $element) !== null) {
                return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredMembership($operand, $elements, $negated);
            }
        }
        $elements = array_map(fn ($element) => $this->row($element, $source), $elements);
        return new InList($operand, $elements, $negated);
    }

    /**
     * Constructs subquery membership after checking the fixed output shape.
     *
     */
    public function inQuery(Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $operand, Query $query, bool $negated, Node $source): Subquery\InQuery|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate
    {
        if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand || (new RowShapes())->query($operand, $query) !== null) {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate($operand, $query, negated: $negated);
        }
        return new Subquery\InQuery($operand, $query, $negated);
    }

    /**
     * Constructs quantified comparison only in its permitted scalar or row role.
     *
     */
    public function quantified(Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $operand, ComparisonOperator $operator, Subquery\Quantifier $quantifier, Query $query, Node $source): Subquery\QuantifiedComparison|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate
    {
        $rows = ($quantifier === Subquery\Quantifier::Any && $operator === ComparisonOperator::Equal) || ($quantifier === Subquery\Quantifier::All && $operator === ComparisonOperator::NotEqual);
        if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand || (!$rows && ($operand instanceof RowOperand || ((new QueryWidth())->of($query) ?? 1) > 1)) || (new RowShapes())->query($operand, $query) !== null) {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate($operand, $query, $operator, $quantifier);
        }
        return new Subquery\QuantifiedComparison($operand, $operator, $quantifier, $query);
    }

    /**
     * Reports a typed input problem without retaining source syntax.
     *
     * @throws RejectedInput When a shape is contradictory
     */
    public function check(?OperandColumns $problem, Node $source): void
    {
        if ($problem !== null) {
            throw new RejectedInput($problem, (new SourceRanges())->span($source)[1] ?? 0, false);
        }
    }

    /**
     * Keeps deferred computations out of ordinary scalar and row consumers.
     *
     * @throws RejectedInput When the operand needs invocation-time preparation
     */
    public function row(Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $operand, Node $source): Scalar|RowOperand
    {
        return $operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand ? $this->scalar($operand, $source) : $operand;
    }
}
