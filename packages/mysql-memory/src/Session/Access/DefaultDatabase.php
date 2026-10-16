<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionCode;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureCode;
use SqlSemantics\Statement\Statement;

/**
 * Resolves an implicit object database before interpreting the object's later clauses.
 *
 * DELETE targets require a default database even when their FROM tables are
 * qualified. Event names precede body diagnostics, and routine CODE names
 * precede the release-build restriction. Observed on 5.6, 8.0 and 8.4.
 * Only the statement's own names are inspected; stored bodies are deferred.
 *
 * @visibility MySqlMemory
 */
final class DefaultDatabase
{
    /**
     * Checks the names whose implicit database is needed before their later clauses.
     *
     * @throws SqlError When an unqualified name requires a current database
     */
    public function check(Statement $statement, Session $session): void
    {
        $database = $session->variables->database;
        if ($statement instanceof Explain) {
            $database = $statement->database->value ?? $database;
            $statement = $statement->statement;
        }
        if ($database !== '') {
            return;
        }
        $names = match (true) {
            $statement instanceof MultipleDelete => $statement->targets,
            $statement instanceof CreateEvent,
            $statement instanceof ShowFunctionCode,
            $statement instanceof ShowProcedureCode => [$statement->name],
            default => [],
        };
        foreach ($names as $name) {
            if ($name->schema === null) {
                throw QueryError::NoDatabase->error();
            }
        }
    }
}
