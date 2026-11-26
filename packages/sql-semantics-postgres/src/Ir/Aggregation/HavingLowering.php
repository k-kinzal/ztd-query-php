<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Aggregation;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference as ParsedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall as ParsedAggregate;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral as ParsedBoolean;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral as ParsedNull;

/**
 * Resolves signed group operands independently of the projected output.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class HavingLowering
{
    /**
     * Resolves the optional group predicate before finalizing its source slots.
     */
    public function lower(?Scalar $condition, SourceBinding $binding, AggregateBinding $aggregate): GroupedComparison|BooleanLiteral|NullLiteral|null
    {
        while ($condition instanceof Grouped) {
            $condition = $condition->operand;
        }
        if ($condition === null) {
            return null;
        }
        if ($condition instanceof ParsedBoolean) {
            return new BooleanLiteral($condition->value);
        }
        if ($condition instanceof ParsedNull) {
            return new NullLiteral();
        }
        Check::input($condition instanceof BinaryOperation, 'HAVING needs a signed group comparison, a Boolean constant or NULL.');
        $operator = (new PredicateLowering())->signedOperator($condition, $aggregate->catalogFirst);

        return new GroupedComparison(
            $operator,
            $this->operand($condition->left, $binding, $aggregate),
            $this->operand($condition->right, $binding, $aggregate),
        );
    }

    /**
     * Binds signed input fields, aggregate arguments and numeric literals.
     */
    public function operand(Scalar $operand, SourceBinding $binding, AggregateBinding $aggregate): Aggregate|ColumnReference|IntegerLiteral|WideIntegerLiteral
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        if ($operand instanceof ParsedAggregate) {
            return $aggregate->lower($operand, $binding);
        }
        if ($operand instanceof ParsedColumn) {
            return $binding->reference($operand);
        }
        $literal = (new ExpressionLowering())->integerLiteral($operand, $aggregate->catalogFirst);
        Check::input($literal !== null, 'A HAVING comparison needs signed group values or signed literals.');

        return $literal;
    }
}
