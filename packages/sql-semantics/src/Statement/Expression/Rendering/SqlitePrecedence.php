<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Rendering;

use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Projection\AliasReference;
use SqlSemantics\Statement\Projection\ColumnOrAlias;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;

/**
 * Binding powers from the fixed SQLite expression grammar, used only for parentheses.
 * @visibility SqlSemantics
 */
final class SqlitePrecedence
{
    /**
     * Every represented binary operator has an explicit grammar binding power.
     * @see https://sqlite.org/lang_expr.html#operators_and_parse_affecting_attributes
     */
    public function binary(E\SqliteBinaryOperator $operator): int
    {
        return match ($operator) {
            E\SqliteBinaryOperator::Or => 10,
            E\SqliteBinaryOperator::And => 20,
            E\SqliteBinaryOperator::Equal, E\SqliteBinaryOperator::DoubleEqual,
            E\SqliteBinaryOperator::NotEqual, E\SqliteBinaryOperator::AngleNotEqual,
            E\SqliteBinaryOperator::Is, E\SqliteBinaryOperator::IsNot,
            E\SqliteBinaryOperator::DistinctFrom, E\SqliteBinaryOperator::NotDistinctFrom => 70,
            E\SqliteBinaryOperator::Less, E\SqliteBinaryOperator::Greater,
            E\SqliteBinaryOperator::LessOrEqual, E\SqliteBinaryOperator::GreaterOrEqual => 80,
            E\SqliteBinaryOperator::BitwiseAnd, E\SqliteBinaryOperator::BitwiseOr,
            E\SqliteBinaryOperator::ShiftLeft, E\SqliteBinaryOperator::ShiftRight => 100,
            E\SqliteBinaryOperator::Add, E\SqliteBinaryOperator::Subtract => 110,
            E\SqliteBinaryOperator::Multiply, E\SqliteBinaryOperator::Divide,
            E\SqliteBinaryOperator::Remainder => 120,
            E\SqliteBinaryOperator::Concatenate => 130,
        };
    }

    /**
     * Parenthesized children are atomic; all other supported forms are enumerated.
     * @throws InvalidConstruction
     */
    public function expression(E\ScalarExpression|C\ScalarInput $expression): int
    {
        return match (true) {
            $expression instanceof E\SqliteBinary, $expression instanceof C\Expression\BinaryInput => $this->binary($expression->operator),
            $expression instanceof E\SqliteUnary, $expression instanceof C\Expression\UnaryInput => $expression->operator === E\SqliteUnaryOperator::Not ? 30 : 150,
            $expression instanceof E\SqliteBetween, $expression instanceof C\Expression\BetweenInput => 60,
            $expression instanceof E\SqliteInList, $expression instanceof E\Subquery\SqliteInQuery,
            $expression instanceof C\Expression\InListInput, $expression instanceof C\Subquery\InQueryInput => 50,
            $expression instanceof E\Conversion\SqliteCollated, $expression instanceof C\Expression\CollationInput => 140,
            $expression instanceof GroupedExpression, $expression instanceof C\Expression\GroupedInput,
            $expression instanceof E\ColumnReference, $expression instanceof C\Expression\ColumnUse,
            $expression instanceof E\BooleanReference, $expression instanceof AliasReference, $expression instanceof ColumnOrAlias,
            $expression instanceof E\NullConstant, $expression instanceof E\SqliteInteger, $expression instanceof E\SqliteReal,
            $expression instanceof E\SqliteText, $expression instanceof E\SqliteBlob, $expression instanceof E\SqliteCurrentTime,
            $expression instanceof E\Conversion\SqliteCast, $expression instanceof C\Expression\CastInput,
            $expression instanceof E\Conditional\SqliteSearchedCase, $expression instanceof E\Conditional\SqliteSimpleCase,
            $expression instanceof C\Conditional\SearchedCaseInput, $expression instanceof C\Conditional\SimpleCaseInput,
            $expression instanceof E\Subquery\SqliteScalarSubquery, $expression instanceof E\Subquery\SqliteExists,
            $expression instanceof C\Subquery\ScalarQueryInput, $expression instanceof C\Subquery\ExistsInput => 1000,
            default => throw new InvalidConstruction('The concrete expression needs an explicit precedence rule.'),
        };
    }

    /**
     * Binary operators associate left: an equal-precedence right child needs grouping.
     */
    public function grouped(E\ScalarExpression|C\ScalarInput $child, E\SqliteBinaryOperator $parent, bool $right): bool
    {
        $childPower = $this->expression($child);
        $parentPower = $this->binary($parent);
        return $childPower < $parentPower || ($right && $childPower === $parentPower);
    }

    /**
     * Prefix operations associate to the right; only a weaker operand needs grouping.
     */
    public function unaryGrouped(E\ScalarExpression|C\ScalarInput $child, E\SqliteUnaryOperator $parent): bool
    {
        return $this->expression($child) < ($parent === E\SqliteUnaryOperator::Not ? 30 : 150);
    }
}
