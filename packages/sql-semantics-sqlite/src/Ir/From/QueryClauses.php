<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\From;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Order;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Relation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\Limit as ParsedLimit;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\NullsOrder;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\OutputOrdinal;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOn;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOperator;

/**
 * Converts supported clauses into values with their local references already bound.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class QueryClauses
{
    /**
     * Maps each join, collecting its references before exporting the final input slots.
     *
     * @return list<Join>
     */
    public function joins(?Relation $source, SourceBinding $binding): array
    {
        if (!$source instanceof JoinChain) {
            return [];
        }
        $predicates = [];
        foreach ($source->steps as $step) {
            Check::input($step->constraint === null || $step->constraint instanceof JoinOn, 'The IR join supports an optional ON predicate.');
            $predicates[] = $this->predicate($step->constraint?->condition, $binding);
        }
        $joins = [];
        foreach ($source->steps as $index => $step) {
            $right = $binding->source($index + 1);
            Check::invariant($right !== null, 'Every join step has a bound table occurrence.');
            $joins[] = new Join($this->kind($step->operator), $right, $predicates[$index]);
        }

        return $joins;
    }

    /**
     * Resolves a join operation without emitting joins that implicitly merge columns.
     *
     * @throws InvalidConstruction When the operation is outside the supported join domain
     */
    public function kind(JoinOperator $operator): JoinKind
    {
        Check::input($operator->valid() && !$operator->natural(), 'The IR join requires an explicit input without NATURAL.');
        $left = $operator->left();
        $right = $operator->right();

        return match (true) {
            $left && $right => JoinKind::Full,
            $left => JoinKind::Left,
            $right => JoinKind::Right,
            in_array(JoinKeyword::Cross, $operator->words, true) => JoinKind::Cross,
            $operator->words === [] || in_array(JoinKeyword::Inner, $operator->words, true) => JoinKind::Inner,
            default => throw new InvalidConstruction('The join operator has no complete IR representation.'),
        };
    }

    /**
     * Converts a filter or ON expression into the closed predicate domain.
     */
    public function predicate(?Scalar $scalar, SourceBinding $binding): Arithmetic|ColumnReference|IntegerLiteral|NullLiteral|Parameter|Comparison|Logical|null
    {
        while ($scalar instanceof Grouped) {
            $scalar = $scalar->operand;
        }
        if ($scalar === null) {
            return null;
        }
        if ($scalar instanceof Binary) {
            $operator = $scalar->operator->value;
            $comparison = match ($scalar->operator) {
                BinaryOperator::Equal, BinaryOperator::DoubleEqual => ComparisonOperator::Equal,
                BinaryOperator::NotEqual, BinaryOperator::BangEqual => ComparisonOperator::NotEqual,
                BinaryOperator::Is, BinaryOperator::IsNotDistinctFrom => ComparisonOperator::Is,
                BinaryOperator::IsNot, BinaryOperator::IsDistinctFrom => ComparisonOperator::IsNot,
                BinaryOperator::Less => ComparisonOperator::Less,
                BinaryOperator::LessOrEqual => ComparisonOperator::LessOrEqual,
                BinaryOperator::Greater => ComparisonOperator::Greater,
                BinaryOperator::GreaterOrEqual => ComparisonOperator::GreaterOrEqual,
                BinaryOperator::And, BinaryOperator::Or,
                BinaryOperator::Add, BinaryOperator::Subtract, BinaryOperator::Multiply, BinaryOperator::Divide, BinaryOperator::Modulo,
                BinaryOperator::BitAnd, BinaryOperator::BitOr, BinaryOperator::ShiftLeft, BinaryOperator::ShiftRight,
                BinaryOperator::Concat, BinaryOperator::Extract, BinaryOperator::ExtractValue => null,
            };
            if ($comparison !== null) {
                $expressions = new ExpressionLowering();

                return new Comparison($comparison, $expressions->lower($scalar->left, $binding), $expressions->lower($scalar->right, $binding));
            }
            $logical = LogicalOperator::tryFrom($operator);
            if ($logical !== null) {
                $left = $this->predicate($scalar->left, $binding);
                $right = $this->predicate($scalar->right, $binding);
                Check::invariant($left !== null && $right !== null, 'A logical operation has both operands.');

                return new Logical($logical, $left, $right);
            }
        }

        return (new ExpressionLowering())->lower($scalar, $binding);
    }

    /**
     * Binds ordering terms by ordinal and fixes their effective direction and NULL placement.
     *
     * @param list<SortTerm> $terms
     * @return list<Order>
     */
    public function ordering(array $terms): array
    {
        $orders = [];
        foreach ($terms as $term) {
            Check::input($term->expression instanceof OutputOrdinal, 'The IR ordering must reference the projection by ordinal.');
            $orders[] = new Order(
                $term->expression->position(),
                $term->direction === SortDirection::Descending,
                $term->nulls === null ? null : $term->nulls === NullsOrder::First,
            );
        }

        return $orders;
    }

    /**
     * Resolves the supported row-bound operands, retaining their written order.
     */
    public function limit(?ParsedLimit $limit, SourceBinding $binding): ?Limit
    {
        if ($limit === null) {
            return null;
        }

        $expressions = new ExpressionLowering();
        $count = $expressions->lower($limit->count, $binding);
        $offset = $limit->offset === null ? null : $expressions->lower($limit->offset, $binding);
        Check::input($count instanceof IntegerLiteral || $count instanceof Parameter, 'LIMIT requires an integer literal or numbered parameter.');
        Check::input($offset === null || $offset instanceof IntegerLiteral || $offset instanceof Parameter, 'OFFSET requires an integer literal or numbered parameter.');

        return new Limit($count, $offset, $limit->comma);
    }
}
