<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Statement\Node;

/**
 * A statement of the stored program language: a compound statement, a flow control statement, a cursor statement or a condition statement.
 *
 * Such a statement is derived at its position in a program, where the
 * parameters, local variables, labels, conditions and cursors declared
 * around it are in scope.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-compound-statements.html.
 *
 * @visibility public
 * @example Telling a program statement from other statements
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN END');
 *     $create->statement->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement // => true
 */
interface ProgramStatement extends Node
{
    /**
     * Derives the facts of the statement in the scope of its position.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void;
}
