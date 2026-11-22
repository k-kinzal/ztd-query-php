<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;

/**
 * A statement of the stored program language: a compound statement, a flow control statement, a cursor statement or a condition statement.
 *
 * Such a statement is derived at its position in a program, where the
 * parameters, local variables, labels, conditions and cursors declared
 * around it are in scope.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-compound-statements.html.
 *
 * @visibility SqlSemantics
 */
interface ProgramStatement extends Node
{
    /**
     * Derives the facts of the statement in the scope of its position.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void;
}
