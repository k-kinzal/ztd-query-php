<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\Literal\IntegerTokenLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRangeLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic as ParsedArithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between as ParsedRange;
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
     * @throws InvalidConstruction When the operand has no representation in this domain
     */
    public function lower(Scalar $expression, SourceBinding|JoinBinding $binding): Arithmetic|ColumnReference|IntegerLiteral|IntegerRange|BooleanLiteral|NullLiteral
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof ParsedArithmetic) {
            $operator = ArithmeticOperator::tryFrom($expression->operator->value);
            Check::input($operator !== null, 'The operator has no checked MySQL signed arithmetic representation.');
            $left = $this->lower($expression->left, $binding);
            $right = $this->lower($expression->right, $binding);
            Check::input(
                ($left instanceof Arithmetic || $left instanceof ColumnReference || $left instanceof IntegerLiteral)
                    && ($right instanceof Arithmetic || $right instanceof ColumnReference || $right instanceof IntegerLiteral),
                'Arithmetic operands must have the signed integer role.',
            );

            return new Arithmetic($operator, $left, $right);
        }
        $token = (new IntegerTokenLowering())->lower($expression);
        if ($token !== null) {
            return new IntegerLiteral($token[0], $token[1], $token[2]);
        }

        return match (true) {
            $expression instanceof ParsedRange => (new IntegerRangeLowering())->lower($expression, $binding),
            $expression instanceof ParsedBoolean => new BooleanLiteral($expression->value),
            $expression instanceof ParsedNull => new NullLiteral(),
            $expression instanceof ColumnUse => $binding->reference($expression),
            default => throw new InvalidConstruction('The operand has no complete MySQL projection representation.'),
        };
    }
}
