<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement;

use SqlSemantics\Statement\Operand;

/**
 * A literal accepted where the grammar requires a constant rather than an expression.
 *
 * Signed literals belong to this domain. Ordinary literals also implement
 * Scalar, while a sign at an expression position is represented by Unary.
 *
 * @visibility public
 * @example Holding a signed constant outside an expression position
 *     new \SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral(true, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3')) instanceof \SqlSemantics\Platform\MySql\Statement\Literal // => true
 */
interface Literal extends Operand
{
}
