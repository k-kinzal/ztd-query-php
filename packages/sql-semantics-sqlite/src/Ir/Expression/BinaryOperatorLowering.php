<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Expression;

use SqlSemantics\Platform\Sqlite\Ir\ArithmeticOperator;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;

/**
 * Selects the complete scalar operator for a parsed binary operation.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class BinaryOperatorLowering
{
    /**
     * Resolves synonymous SQLite spellings to the same operation.
     *
     * @throws InvalidConstruction When the operator has no complete scalar representation
     */
    public function lower(BinaryOperator $operator): ArithmeticOperator|ComparisonOperator|LogicalOperator
    {
        return match ($operator) {
            BinaryOperator::Add, BinaryOperator::Subtract, BinaryOperator::Multiply, BinaryOperator::Divide, BinaryOperator::Modulo,
            BinaryOperator::BitAnd, BinaryOperator::BitOr, BinaryOperator::ShiftLeft, BinaryOperator::ShiftRight
                => ArithmeticOperator::from($operator->value),
            BinaryOperator::And => LogicalOperator::And,
            BinaryOperator::Or => LogicalOperator::Or,
            BinaryOperator::Equal, BinaryOperator::DoubleEqual => ComparisonOperator::Equal,
            BinaryOperator::NotEqual, BinaryOperator::BangEqual => ComparisonOperator::NotEqual,
            BinaryOperator::Is, BinaryOperator::IsNotDistinctFrom => ComparisonOperator::Is,
            BinaryOperator::IsNot, BinaryOperator::IsDistinctFrom => ComparisonOperator::IsNot,
            BinaryOperator::Less => ComparisonOperator::Less,
            BinaryOperator::LessOrEqual => ComparisonOperator::LessOrEqual,
            BinaryOperator::Greater => ComparisonOperator::Greater,
            BinaryOperator::GreaterOrEqual => ComparisonOperator::GreaterOrEqual,
            BinaryOperator::Concat, BinaryOperator::Extract, BinaryOperator::ExtractValue
                => throw new InvalidConstruction('This operation has no complete SQLite scalar representation.'),
        };
    }
}
