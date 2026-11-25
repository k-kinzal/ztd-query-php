<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\Membership\IntegerMembershipLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparisonLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;

/**
 * Resolves the ordered operands and native dispatch of an ordinary comparison.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ComparisonLowering
{
    /**
     * Binds the compared physical or literal values and their checked operator.
     * @throws InvalidConstruction When the operands require another native coercion
     */
    public function lower(Comparison $test, SourceBinding|JoinBinding $binding): IntegerComparison|TextComparison
    {
        $text = (new TextComparisonLowering())->lower($test, $binding);
        if ($text !== null) {
            return $text;
        }
        $operator = IntegerOperator::from($test->operator->value);

        return new IntegerComparison($operator, $this->operand($test->left, $binding), $this->operand($test->right, $binding));
    }

    /**
     * Resolves an integer comparison atom at its exact native binding.
     * @throws InvalidConstruction When the operand is outside the signed comparison domain
     */
    public function operand(Scalar $value, SourceBinding|JoinBinding $binding): ColumnReference|IntegralColumnReference|IntegerLiteral|RangeIntegerLiteral|NullLiteral
    {
        $operand = (new IntegerMembershipLowering())->operand($value, $binding);
        if ($operand instanceof RangeIntegerLiteral && IntegerStorage::Standard->accepts($operand)) {
            return new IntegerLiteral($operand->decimal, $operand->leadingZeroes, $operand->negativeZero);
        }

        return $operand;
    }
}
