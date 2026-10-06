<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Derives the cursor statements OPEN, FETCH and CLOSE.
 *
 * Rule: MYSQL-PROGRAM-CURSORS-001. The cursor must be declared in an
 * enclosing block (ER_SP_CURSOR_MISMATCH), and every target of FETCH must
 * be a parameter or local variable in scope (ER_SP_UNDECLARED_VAR). Whether
 * the cursor is open, and whether the number of targets equals the number
 * of columns of the cursor query, is checked only when the program runs.
 * Terminates: the lists are finite.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cursors.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CursorFacts
{
    /**
     * Reports a cursor that is not declared around the statement.
     */
    public function cursor(Name $cursor, Derivation $derivation, ProgramScope $scope): void
    {
        if (!$scope->holds($scope->cursors, $cursor)) {
            $derivation->report(new ProgramProblem(ProgramRule::UndefinedCursor, $cursor->value));
        }
    }

    /**
     * Reports every name that is no parameter or local variable in scope.
     *
     * @param list<Name> $variables
     */
    public function variables(array $variables, Derivation $derivation, ProgramScope $scope): void
    {
        foreach ($variables as $variable) {
            if (!$scope->variable($variable)) {
                $derivation->report(new ProgramProblem(ProgramRule::UndeclaredVariable, $variable->value));
            }
        }
    }
}
