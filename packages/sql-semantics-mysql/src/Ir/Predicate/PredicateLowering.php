<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\ExpressionLowering;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison as ParsedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;

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
    public function lower(?Scalar $condition, SourceBinding|JoinBinding $binding): BooleanLiteral|IntegerComparison|NullLiteral|null
    {
        while ($condition instanceof Grouped) {
            $condition = $condition->operand;
        }
        if ($condition === null) {
            return null;
        }
        $expression = new ExpressionLowering();
        if ($condition instanceof ParsedComparison) {
            $operator = IntegerOperator::tryFrom($condition->operator->value);
            Check::input($operator !== null, 'A predicate comparison needs a supported integer operator.');
            $left = $expression->lower($condition->left, $binding);
            $right = $expression->lower($condition->right, $binding);
            Check::input(($left instanceof ColumnReference || $left instanceof IntegerLiteral)
                && ($right instanceof ColumnReference || $right instanceof IntegerLiteral), 'A predicate comparison needs two signed integer operands.');

            return new IntegerComparison($operator, $left, $right);
        }
        $constant = $expression->lower($condition, $binding);
        Check::input($constant instanceof BooleanLiteral || $constant instanceof NullLiteral, 'A filter needs a Boolean or signed integer comparison.');

        return $constant;
    }
}
