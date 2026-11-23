<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\From;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Order;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Relation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Statement\Query\Limit as ParsedLimit;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\NullsOrder;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\OutputOrdinal;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOn;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOperator;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinUsing;

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
    public function joins(?Relation $source, SourceBinding $binding, BoundStatement $operation): array
    {
        if (!$source instanceof JoinChain) {
            return [];
        }
        $predicates = [];
        foreach ($source->steps as $position => $step) {
            $predicates[] = match (true) {
                $step->operator->natural(), $step->constraint instanceof JoinUsing
                    => (new JoinKeyLowering())->predicate($source, $step, $binding, $operation, $position),
                $step->constraint instanceof JoinOn => $this->predicate($step->constraint->condition, $binding),
                $step->constraint === null => null,
            };
        }
        $joins = [];
        foreach ($source->steps as $index => $step) {
            $right = $binding->input($index + 1);
            Check::invariant($right instanceof TableSource || $right instanceof LocalSource, 'Every join step has a bound input occurrence.');
            $joins[] = new Join($this->kind($step->operator), $right, $predicates[$index]);
        }

        return $joins;
    }

    /**
     * Resolves the effective join operation after normalizing merged keys.
     *
     * @throws InvalidConstruction When the operation is outside the supported join domain
     */
    public function kind(JoinOperator $operator): JoinKind
    {
        Check::input($operator->valid(), 'The IR requires a valid SQLite join operator.');
        $left = $operator->left();
        $right = $operator->right();

        return match (true) {
            $left && $right => JoinKind::Full,
            $left => JoinKind::Left,
            $right => JoinKind::Right,
            in_array(JoinKeyword::Cross, $operator->words, true) => JoinKind::Cross,
            $operator->words === [], $operator->natural(), in_array(JoinKeyword::Inner, $operator->words, true) => JoinKind::Inner,
            default => throw new InvalidConstruction('The join operator has no complete IR representation.'),
        };
    }

    /**
     * Converts a filter or ON expression into the closed predicate domain.
     */
    public function predicate(?Scalar $scalar, SourceBinding $binding): Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression|null
    {
        return $scalar === null ? null : (new ExpressionLowering())->lower($scalar, $binding);
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
