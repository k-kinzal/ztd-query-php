<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Routine;

use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;

/**
 * Finds an executable routine declaration, keeping absent installed bodies out of execution.
 *
 * @visibility MySqlMemory
 */
final class Body
{
    /**
     * Answers the parsed declaration of a user-created routine.
     *
     * @throws SqlError When only the public installed signature is available
     */
    public static function of(Routine $routine): CreateProcedure|CreateFunction
    {
        if ($routine->statement === null) {
            throw StatementError::NotSupportedYet->error('execution or source of installed sys routines');
        }

        return $routine->statement;
    }
}
