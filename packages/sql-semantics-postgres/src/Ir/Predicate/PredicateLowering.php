<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

use SqlSemantics\Platform\PostgreSql\Ir\Arithmetic;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparisonLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation as ParsedComparison;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference as ParsedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\BooleanOperation as ParsedLogical;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\BooleanOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Negation as ParsedNot;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTest as ParsedNullTest;

/**
 * Resolves the closed Boolean, NULL and signed comparison roles.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class PredicateLowering
{
    /**
     * Resolves native signed integer operands or a constant truth value.
     */
    public function lower(?Scalar $condition, SourceBinding|JoinBinding $binding, bool $catalogFirst): BooleanColumnReference|BooleanLiteral|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|LogicalPredicate|NullLiteral|NullTest|TextComparison|TruthTest|null
    {
        return $condition === null ? null : $this->lowerValue($condition, $binding, $catalogFirst);
    }

    /**
     * Resolves a checked predicate or truth constant.
     */
    public function lowerValue(Scalar $condition, SourceBinding|JoinBinding $binding, bool $catalogFirst): BooleanColumnReference|BooleanLiteral|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|LogicalPredicate|NullLiteral|NullTest|TextComparison|TruthTest
    {
        while ($condition instanceof Grouped) {
            $condition = $condition->operand;
        }
        if ($condition instanceof ParsedLogical) {
            $operator = match ($condition->operator) {
                BooleanOperator::And => LogicalOperator::And,
                BooleanOperator::Or => LogicalOperator::Or,
            };

            return new LogicalPredicate($operator, $this->lowerValue($condition->left, $binding, $catalogFirst), $this->lowerValue($condition->right, $binding, $catalogFirst));
        }
        if ($condition instanceof ParsedNot) {
            return new LogicalPredicate(LogicalOperator::Not, $this->lowerValue($condition->operand, $binding, $catalogFirst));
        }
        if ($condition instanceof ParsedNullTest) {
            return $this->nullTest($condition, $binding, $catalogFirst);
        }
        if ($condition instanceof ParsedComparison) {
            return $this->comparison($condition, $binding, $catalogFirst);
        }

        return $this->leaf($condition, $binding, $catalogFirst);
    }

    /**
     * Resolves a built-in text comparison or the exact pair of signed roles.
     */
    public function comparison(ParsedComparison $condition, SourceBinding|JoinBinding $binding, bool $catalogFirst): IntegerComparison|TextComparison
    {
        $text = (new TextComparisonLowering())->lower($condition, $binding, $catalogFirst);
        if ($text !== null) {
            return $text;
        }
        return new IntegerComparison(
            $this->signedOperator($condition, $catalogFirst),
            $this->signedOperand($condition->left, $binding, $catalogFirst),
            $this->signedOperand($condition->right, $binding, $catalogFirst),
        );
    }

    /**
     * Resolves an exact builtin signed comparison in row or group scope.
     */
    public function signedOperator(ParsedComparison $condition, bool $catalogFirst): IntegerOperator
    {
        $qualifiers = array_column($condition->operator->qualifiers, 'value');
        $allowed = $catalogFirst ? [[], ['pg_catalog']] : [['pg_catalog']];
        Check::input(in_array($qualifiers, $allowed, true), 'A predicate comparison must resolve to a pg_catalog operator.');
        $operator = IntegerOperator::tryFrom($condition->operator->name->value);
        Check::input($operator !== null, 'A predicate comparison needs a supported integer operator.');

        return $operator;
    }

    /**
     * Resolves a checked signed field or signed literal of either native width.
     */
    public function signedOperand(Scalar $operand, SourceBinding|JoinBinding $binding, bool $catalogFirst): ColumnReference|IntegerLiteral|WideIntegerColumnReference|WideIntegerLiteral
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        $value = $operand instanceof ParsedColumn ? $binding->field($operand) : (new ExpressionLowering())->integerLiteral($operand, $catalogFirst);
        Check::input(
            $value instanceof ColumnReference || $value instanceof IntegerLiteral
                || $value instanceof WideIntegerColumnReference || $value instanceof WideIntegerLiteral,
            'A predicate comparison needs two signed integer operands.',
        );

        return $value;
    }

    /**
     * Preserves the explicit scalar or predicate tested for NULL.
     */
    public function nullTest(ParsedNullTest $condition, SourceBinding|JoinBinding $binding, bool $catalogFirst): NullTest
    {
        $expression = new ExpressionLowering();
        $operand = $condition->operand;
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        if ($operand instanceof ParsedColumn) {
            return new NullTest($binding->field($operand), $condition->negated);
        }
        $tested = $operand instanceof ParsedLogical || $operand instanceof ParsedNot || $operand instanceof ParsedNullTest || $operand instanceof ParsedComparison
            ? $this->lowerValue($operand, $binding, $catalogFirst)
            : $expression->lower($operand, $binding, $catalogFirst);
        Check::input(!$tested instanceof Arithmetic && !$tested instanceof BuiltinCast && !$tested instanceof CollatedText && !$tested instanceof IntegerCase && !$tested instanceof WideIntegerLiteral && !$tested instanceof TextLiteral, 'A null test needs a supported scalar value.');

        return new NullTest($tested, $condition->negated);
    }

    /**
     * Resolves a truth constant or a comparison with its own scalar role.
     */
    public function leaf(Scalar $condition, SourceBinding|JoinBinding $binding, bool $catalogFirst): BooleanColumnReference|BooleanLiteral|IntegerDistinctness|IntegerMembership|IntegerRange|NullLiteral|TruthTest
    {
        if ($condition instanceof ParsedColumn) {
            $field = $binding->field($condition);
            Check::input($field instanceof BooleanColumnReference, 'A filter needs a Boolean or signed integer comparison.');

            return $field;
        }
        $value = (new ExpressionLowering())->lower($condition, $binding, $catalogFirst);
        Check::input(
            $value instanceof BooleanLiteral || $value instanceof NullLiteral || $value instanceof IntegerRange || $value instanceof IntegerDistinctness || $value instanceof IntegerMembership || $value instanceof TruthTest,
            'A filter needs a Boolean or signed integer comparison.',
        );

        return $value;
    }
}
