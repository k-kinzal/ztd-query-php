<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression;

use SqlSemantics\Statement\Operand;

/**
 * A stored computation whose intrinsic operand constraint is checked on invocation.
 *
 * This domain belongs to program instructions, not general Scalar positions.
 * Its operands retain their own semantic roles and resolve in the invocation's
 * scope. An unreachable instruction does not evaluate or prepare this value.
 *
 * @visibility public
 * @example Keeping stored arithmetic outside Scalar
 *     $value = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3')]));
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped($value)) instanceof \SqlSemantics\Statement\Scalar // => false
 */
interface DeferredOperand extends Operand
{
    /**
     * Exposes the dependencies in their preparation order.
     *
     * @return non-empty-list<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|DeferredOperand>
     */
    public function operands(): array;

    /**
     * Answers the first fixed intrinsic operand constraint of this computation.
     */
    public function problem(): \SqlSemantics\Statement\Fact\Diagnostic;
}
