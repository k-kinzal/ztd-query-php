<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount;
use SqlSemantics\Statement\Fact\Diagnostic;

/**
 * Maps semantic call and window constraints to their public MySQL conditions.
 *
 * @visibility MySqlMemory
 */
final class CallErrors
{
    /**
     * Preserves native function spelling when the input phase requires it.
     */
    public function error(Diagnostic $diagnostic, bool $originalCase = false): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof WrongArgumentCount => QueryError::WrongParameterCountToNativeFunction->error($diagnostic->function->value),
            $diagnostic instanceof NamedArgument => QueryError::WrongParametersToNativeFunction->error($originalCase ? $diagnostic->function->value : strtolower($diagnostic->function->value)),
            $diagnostic instanceof ReservedFunction => QueryError::NativeFunctionRejected->error($diagnostic->function->value),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidNamedConstant => ($diagnostic->nullName ? StatementError::ReservedSyntax : StatementError::WrongArguments)->error('NAME_CONST'),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint => ($diagnostic->positional ? QueryError::WindowPositionalOrdering : QueryError::WindowFrameIllegal)->error('<unnamed window>'),
            $diagnostic instanceof UnsupportedWindowing => StatementError::NotSupportedYet->error($diagnostic->limit->value),
            default => null,
        };
    }
}
