<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement;

use SqlSemantics\Statement\Scalar;

/**
 * A literal that is also an ordinary expression, without a separate unary sign.
 *
 * This role is accepted by SIGNAL information items. A signed literal is
 * restricted to constant positions; an arithmetic sign in an expression is
 * a Unary node and is not a literal expression.
 *
 * @visibility public
 * @example Reading a literal expression
 *     new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3') instanceof \SqlSemantics\Platform\MySql\Statement\LiteralExpression // => true
 */
interface LiteralExpression extends Literal, Scalar
{
}
