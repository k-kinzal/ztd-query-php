<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Problem;

/**
 * The partition role of a refused operand, before general scalar binding.
 *
 * @visibility public
 * @example Identifying a contextual partition restriction
 *     \SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::SessionState->name // => 'SessionState'
 */
enum OperandCondition
{
    case Function;
    case SessionState;
    case Subquery;
}
