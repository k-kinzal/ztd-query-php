<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation;

/**
 * Intrinsic expression roles excluded from a generated column definition.
 *
 * @visibility public
 * @example Distinguishing a forbidden call from a subquery
 *     \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\OperandKind::FunctionCall !== \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\OperandKind::Subquery // => true
 */
enum OperandKind
{
    case FunctionCall;
    case Subquery;
    case Variable;
}
