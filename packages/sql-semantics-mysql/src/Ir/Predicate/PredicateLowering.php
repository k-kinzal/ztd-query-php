<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate;

use SqlSemantics\Platform\MySql\Ir\Arithmetic;
use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\ExpressionLowering;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparisonLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthTest;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison as ParsedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical as ParsedLogical;
use SqlSemantics\Platform\MySql\Statement\Expression\Not as ParsedNot;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest as ParsedNullTest;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest as ParsedTruthTest;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

/**
 * Resolves the closed Boolean, NULL and signed comparison roles.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class PredicateLowering
{
    /**
     * Resolves native signed integer operands or a constant truth value.
     */
    public function lower(?Scalar $condition, SourceBinding|JoinBinding $binding): BooleanLiteral|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|LogicalPredicate|NullLiteral|NullTest|TruthTest|null
    {
        return $condition === null ? null : $this->lowerValue($condition, $binding);
    }

    /**
     * Resolves a checked predicate or truth constant.
     */
    public function lowerValue(Scalar $condition, SourceBinding|JoinBinding $binding): BooleanLiteral|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|LogicalPredicate|NullLiteral|NullTest|TruthTest
    {
        while ($condition instanceof Grouped) {
            $condition = $condition->operand;
        }
        if ($condition instanceof ParsedLogical) {
            $operator = LogicalOperator::from($condition->operator->value);

            return new LogicalPredicate($operator, $this->lowerValue($condition->left, $binding), $this->lowerValue($condition->right, $binding));
        }
        if ($condition instanceof ParsedNot) {
            return new LogicalPredicate(LogicalOperator::Not, $this->lowerValue($condition->operand, $binding));
        }
        if ($condition instanceof ParsedTruthTest) {
            return (new TruthLowering())->lower($condition, $binding);
        }
        if ($condition instanceof ParsedNullTest) {
            return $this->nullTest($condition, $binding);
        }
        $expression = new ExpressionLowering();
        if ($condition instanceof ParsedComparison) {
            $text = (new TextComparisonLowering())->lower($condition, $binding);
            if ($text !== null) {
                return $text;
            }
            $operator = IntegerOperator::tryFrom($condition->operator->value);
            Check::input($operator !== null, 'A predicate comparison needs a supported integer operator.');
            $left = $expression->lower($condition->left, $binding);
            $right = $expression->lower($condition->right, $binding);
            Check::input(($left instanceof ColumnReference || $left instanceof IntegerLiteral)
                && ($right instanceof ColumnReference || $right instanceof IntegerLiteral), 'A predicate comparison needs two signed integer operands.');

            return new IntegerComparison($operator, $left, $right);
        }
        $constant = $expression->lower($condition, $binding);
        Check::input($constant instanceof BooleanLiteral || $constant instanceof NullLiteral || $constant instanceof IntegerRange || $constant instanceof IntegerMembership, 'A filter needs a Boolean or signed integer comparison.');

        return $constant;
    }

    /**
     * Keeps the native scalar reference and preserves the test's polarity.
     */
    public function nullTest(ParsedNullTest $condition, SourceBinding|JoinBinding $binding): NullTest
    {
        $operand = $condition->operand;
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        if ($operand instanceof ColumnUse) {
            return new NullTest($binding->field($operand), $condition->negated);
        }
        $tested = $operand instanceof ParsedLogical || $operand instanceof ParsedNot || $operand instanceof ParsedNullTest || $operand instanceof ParsedTruthTest || $operand instanceof ParsedComparison
            ? $this->lowerValue($operand, $binding)
            : (new ExpressionLowering())->lower($operand, $binding);
        Check::input(!$tested instanceof Arithmetic, 'A null test needs a supported scalar value.');

        return new NullTest($tested, $condition->negated);
    }
}
