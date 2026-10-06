<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Statement;

/**
 * Derives the statements of a stored program at their position.
 *
 * Rule: MYSQL-PROGRAM-BODY-001. A statement of the stored program language
 * is derived in the scope of its position. Any other SQL statement is first
 * checked against the restrictions of the program kind
 * (MYSQL-PROGRAM-RESTRICTIONS-001) and, in a trigger, a SET statement
 * against MYSQL-TRIGGER-ASSIGNMENT-001. A query is then derived as a nested
 * query whose enclosing scope holds the parameters and local variables, so
 * it returns no rows of the statement that defines the program; any other
 * statement as an inspected request read at the environment of the
 * position, so it sees the same parameters and variables: its parts receive
 * their facts and its diagnostics are kept, while it declares nothing and
 * returns nothing. Limit: a nested statement finds a column of its own
 * tables before a variable of the same name, where the server prefers the
 * variable; that needs a resolution order the core does not offer. An
 * external routine body holds no SQL and derives nothing. Terminates: one
 * pass over the list; nested statements are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/local-variable-scope.html,
 * https://dev.mysql.com/doc/refman/8.4/en/stored-program-restrictions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BodyFacts
{
    /**
     * Derives the statements of a list in order.
     *
     * @param list<Node> $statements
     */
    public function statements(array $statements, Derivation $derivation, ProgramScope $scope): void
    {
        foreach ($statements as $statement) {
            $this->statement($statement, $derivation, $scope);
        }
    }

    /**
     * Derives one statement of a program.
     */
    public function statement(Node $statement, Derivation $derivation, ProgramScope $scope): void
    {
        if ($statement instanceof ProgramStatement) {
            $statement->deriveProgram($derivation, $scope);

            return;
        }
        if ($statement instanceof Statement && $scope->kind !== null) {
            (new ProgramRestrictions())->check($statement, $scope->kind, $derivation);
        }
        if ($statement instanceof SetVariables && $scope->time !== null && $scope->event !== null) {
            (new TriggerAssignments())->check($statement, $scope->time, $scope->event, $derivation, $scope);
        }
        if ($statement instanceof Query) {
            $derivation->query($statement, $scope->environment);
        } elseif ($statement instanceof Statement) {
            $derivation->inspected($statement, $scope->environment);
        }
    }
}
