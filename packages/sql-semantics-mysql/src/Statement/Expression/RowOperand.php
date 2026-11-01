<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

use SqlSemantics\Statement\Operand;

/**
 * A row-valued operand with its own role, separate from Scalar.
 *
 * Ordinary scalar positions do not accept this interface. A comparison or a
 * membership predicate explicitly admits it alongside scalar expressions.
 * Stored program conditions, initializers, CASE operands and return values can
 * retain a row; their scalar-use condition is checked when the instruction is reached.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html.
 *
 * @visibility public
 * @example Keeping a row outside the scalar domain
 *     (new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')])) instanceof \SqlSemantics\Statement\Scalar // => false
 */
interface RowOperand extends Operand
{
}
