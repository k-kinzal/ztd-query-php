<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation as ParsedComparison;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;

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
    public function lower(?Scalar $condition, SourceBinding|JoinBinding $binding, bool $catalogFirst): BooleanLiteral|IntegerComparison|NullLiteral|null
    {
        while ($condition instanceof Grouped) {
            $condition = $condition->operand;
        }
        if ($condition === null) {
            return null;
        }
        $expression = new ExpressionLowering();
        if ($condition instanceof ParsedComparison) {
            $qualifiers = array_column($condition->operator->qualifiers, 'value');
            Check::input(($qualifiers === [] && $catalogFirst) || $qualifiers === ['pg_catalog'], 'A predicate comparison must resolve to a pg_catalog operator.');
            $operator = IntegerOperator::tryFrom($condition->operator->name->value);
            Check::input($operator !== null, 'A predicate comparison needs a supported integer operator.');
            $left = $expression->lower($condition->left, $binding, $catalogFirst);
            $right = $expression->lower($condition->right, $binding, $catalogFirst);
            Check::input(($left instanceof ColumnReference || $left instanceof IntegerLiteral)
                && ($right instanceof ColumnReference || $right instanceof IntegerLiteral), 'A predicate comparison needs two signed integer operands.');

            return new IntegerComparison($operator, $left, $right);
        }
        $constant = $expression->lower($condition, $binding, $catalogFirst);
        Check::input($constant instanceof BooleanLiteral || $constant instanceof NullLiteral, 'A filter needs a Boolean or signed integer comparison.');

        return $constant;
    }
}
