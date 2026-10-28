<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression;

use SqlSemantics\Statement\Operand;

/**
 * A stored computation whose intrinsic operand constraint is checked on invocation.
 *
 * Program instructions and complete requests that explicitly admit refused
 * operands use this domain; general Scalar positions do not. Its operands
 * retain their semantic roles and resolve in the request or invocation scope. An unreachable instruction does not evaluate or prepare this value.
 *
 * @visibility public
 * @example Keeping stored arithmetic outside Scalar
 *     $value = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3')]));
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped($value)) instanceof \SqlSemantics\Statement\Scalar // => false
 */
interface DeferredOperand extends Operand
{
    /**
     * Exposes scalar dependencies in their preparation order. Query dependencies
     * are retained separately by their owning form and may have no scalar operands.
     *
     * @return list<\SqlSemantics\Platform\MySql\Statement\Call\Window\RoutineVariable|\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|DeferredOperand>
     */
    public function operands(): array;

    /**
     * Answers the first fixed intrinsic operand constraint of this computation.
     */
    public function problem(): \SqlSemantics\Statement\Fact\Diagnostic;
}
