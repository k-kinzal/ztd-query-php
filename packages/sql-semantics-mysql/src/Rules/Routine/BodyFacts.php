<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Statement;

/**
 * Derives the statements of a stored program at their position.
 *
 * Rule: MYSQL-PROGRAM-BODY-001. A statement of the stored program language
 * is derived in the scope of its position. A query is derived as a nested
 * query whose enclosing scope holds the parameters and local variables, so
 * it returns no rows of the statement that defines the program. Any other
 * SQL statement is derived as an inspected request: its parts receive their
 * facts and its diagnostics are kept, while it declares nothing and returns
 * nothing. Limits: a nested query finds a column of its own tables before a
 * variable of the same name, where the server prefers the variable; a
 * statement that is no query does not see the variables at all. Both need a
 * derivation entry the core does not offer. An external routine body holds
 * no SQL and derives nothing. Terminates: one pass over the list; nested
 * statements are strict parts.
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
        } elseif ($statement instanceof Query) {
            $derivation->query($statement, $scope->environment);
        } elseif ($statement instanceof Statement) {
            $derivation->inspected($statement);
        }
    }
}
