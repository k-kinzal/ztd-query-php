<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation;

/**
 * The column expression a refused operand belongs to: a generation expression or an expression default.
 *
 * Both refuse subqueries, variables, row values and stored functions. A generation
 * expression also refuses every function whose result depends on more than its
 * arguments; an expression default only those that read the server's state or wait.
 *
 * @visibility public
 * @example Distinguishing an expression default from a generation expression
 *     \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole::Default !== \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole::Generated // => true
 */
enum ExpressionRole
{
    case Generated;
    case Default;
}
