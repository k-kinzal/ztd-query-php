<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

use SqlSemantics\Platform\PostgreSql\Ir\Arithmetic;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCase;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation as ParsedComparison;
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
    public function lower(?Scalar $condition, SourceBinding|JoinBinding $binding, bool $catalogFirst): BooleanLiteral|IntegerComparison|IntegerRange|LogicalPredicate|NullLiteral|NullTest|null
    {
        return $condition === null ? null : $this->lowerValue($condition, $binding, $catalogFirst);
    }

    /**
     * Resolves a checked predicate or truth constant.
     */
    public function lowerValue(Scalar $condition, SourceBinding|JoinBinding $binding, bool $catalogFirst): BooleanLiteral|IntegerComparison|IntegerRange|LogicalPredicate|NullLiteral|NullTest
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
        $expression = new ExpressionLowering();
        if ($condition instanceof ParsedNullTest) {
            $operand = $condition->operand;
            while ($operand instanceof Grouped) {
                $operand = $operand->operand;
            }
            $tested = $operand instanceof ParsedLogical || $operand instanceof ParsedNot || $operand instanceof ParsedNullTest || $operand instanceof ParsedComparison
                ? $this->lowerValue($operand, $binding, $catalogFirst)
                : $expression->lower($operand, $binding, $catalogFirst);
            Check::input(!$tested instanceof Arithmetic && !$tested instanceof IntegerCase, 'A null test needs a supported scalar value.');

            return new NullTest($tested, $condition->negated);
        }
        if ($condition instanceof ParsedComparison) {
            $qualifiers = array_column($condition->operator->qualifiers, 'value');
            $allowed = $catalogFirst ? [[], ['pg_catalog']] : [['pg_catalog']];
            Check::input(in_array($qualifiers, $allowed, true), 'A predicate comparison must resolve to a pg_catalog operator.');
            $operator = IntegerOperator::tryFrom($condition->operator->name->value);
            Check::input($operator !== null, 'A predicate comparison needs a supported integer operator.');
            $left = $expression->lower($condition->left, $binding, $catalogFirst);
            $right = $expression->lower($condition->right, $binding, $catalogFirst);
            Check::input(($left instanceof ColumnReference || $left instanceof IntegerLiteral)
                && ($right instanceof ColumnReference || $right instanceof IntegerLiteral), 'A predicate comparison needs two signed integer operands.');

            return new IntegerComparison($operator, $left, $right);
        }
        $constant = $expression->lower($condition, $binding, $catalogFirst);
        Check::input($constant instanceof BooleanLiteral || $constant instanceof NullLiteral || $constant instanceof IntegerRange, 'A filter needs a Boolean or signed integer comparison.');

        return $constant;
    }
}
