<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\Literal\IntegerTokenLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRangeLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\Membership\IntegerMembershipLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic as ParsedArithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between as ParsedRange;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList as ParsedMembership;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral as ParsedBoolean;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

/**
 * Lowers resolved signed integer projection operands.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ExpressionLowering
{
    /**
     * Converts a resolved operand, refusing expressions outside the projection contract.
     *
     * @param bool $wide Whether the enclosing conversion admits native 64-bit literals
     * @return ($wide is true ? Arithmetic|ColumnReference|IntegerLiteral|IntegerMembership|IntegerRange|BooleanLiteral|NullLiteral|RangeIntegerLiteral : Arithmetic|ColumnReference|IntegerLiteral|IntegerMembership|IntegerRange|BooleanLiteral|NullLiteral)
     * @throws InvalidConstruction When the operand has no representation in this domain
     */
    public function lower(Scalar $expression, SourceBinding|JoinBinding $binding, bool $wide = false): Arithmetic|ColumnReference|IntegerLiteral|IntegerMembership|IntegerRange|BooleanLiteral|NullLiteral|RangeIntegerLiteral
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof ParsedArithmetic) {
            return $this->binary($expression, $binding, $wide);
        }
        $token = (new IntegerTokenLowering())->lower($expression);
        if ($token !== null) {
            if ($wide) {
                $magnitude = ltrim($token[0], '-');
                $limit = $token[0][0] === '-' ? '2147483648' : '2147483647';
                if (strlen($magnitude) > 10 || (strlen($magnitude) === 10 && strcmp($magnitude, $limit) > 0)) {
                    return new RangeIntegerLiteral($token[0], $token[1], $token[2]);
                }
            }

            return new IntegerLiteral($token[0], $token[1], $token[2]);
        }

        return match (true) {
            $expression instanceof ParsedMembership => (new IntegerMembershipLowering())->lower($expression, $binding),
            $expression instanceof ParsedRange => (new IntegerRangeLowering())->lower($expression, $binding),
            $expression instanceof ParsedBoolean => new BooleanLiteral($expression->value),
            $expression instanceof ParsedNull => new NullLiteral(),
            $expression instanceof ColumnUse => $binding->reference($expression),
            default => throw new InvalidConstruction('The operand has no complete MySQL projection representation.'),
        };
    }

    /**
     * Resolves a signed arithmetic operation and its two checked operands.
     * @throws InvalidConstruction When either operand has a different static role
     */
    public function binary(ParsedArithmetic $expression, SourceBinding|JoinBinding $binding, bool $wide): Arithmetic
    {
        $operator = ArithmeticOperator::tryFrom($expression->operator->value);
        Check::input($operator !== null, 'The operator has no checked MySQL signed arithmetic representation.');
        $left = $this->lower($expression->left, $binding, $wide);
        $right = $this->lower($expression->right, $binding, $wide);
        Check::input(
            ($left instanceof Arithmetic || $left instanceof ColumnReference || $left instanceof IntegerLiteral || $left instanceof RangeIntegerLiteral)
                && ($right instanceof Arithmetic || $right instanceof ColumnReference || $right instanceof IntegerLiteral || $right instanceof RangeIntegerLiteral),
            'Arithmetic operands must have the signed integer role.',
        );

        return new Arithmetic($operator, $left, $right);
    }
}
