<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Hint\Hints;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\Problem\Reading;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Session;
use SqlSemantics\Statement\Operation;

/**
 * Prepares an operation independently of its input transport. Resource-group
 * binding conditions follow name resolution, including a preparation failure;
 * SET_VAR and registration conditions precede it. Observed on MySQL 8.0–9.1.
 *
 * @visibility MySqlMemory
 */
final class Preparation
{
    /**
     * Resolves the operation while preserving native condition order and restoring
     * temporary SET_VAR values on both success and failure.
     *
     * @throws SqlError When preparation rejects the operation
     */
    public function check(Operation $operation, Session $session): void
    {
        (new Execution($session))->parsing($operation, $operation->facts->warnings);
        if ($operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\UseDatabase) {
            throw \MySqlMemory\Error\Family\StatementError::UnsupportedPreparedStatement->error();
        }
        $this->program($operation, $session);
        (new Reading())->read($operation, $session);
        (new DefaultDatabase())->check($operation->statement, $session);
        (new DefaultDatabase())->referenced($operation, $session);
        $application = (new Hints())->apply($operation->statement, $session, true);
        try {
            $application->report($session);
            (new Problems())->raise($operation, $session);
        } catch (SqlError $error) {
            if (!$error->recorded) {
                $session->diagnostics->error($error->getCode(), $error->getMessage(), $error->signalled);
            }
            foreach ($error->following as [$code, $message]) {
                $session->diagnostics->error($code, $message);
            }
            throw new SqlError($error->error, $error->getMessage(), $error, [], $error->signalled, $error->getCode(), true);
        } finally {
            $application->restore($session);
            foreach ($application->resourceWarnings as [$code, $message]) {
                $session->diagnostics->warning($code, $message);
            }
        }
    }

    /**
     * Applies the routine and trigger protocol restriction after their reading
     * conditions, before catalog existence and invocation checks. A valid stored
     * body may be defined through the text protocol but not COM_STMT_PREPARE.
     * Observed on MySQL 5.6, 5.7, 8.0, 8.4 and 9.1.
     *
     * @throws SqlError When a header, body or protocol condition refuses preparation
     */
    public function program(Operation $operation, Session $session): void
    {
        $statement = $operation->statement;
        if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction && !$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure && !$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger) {
            return;
        }
        $programs = new \MySqlMemory\Command\Program\ProgramProblems();
        $programs->definer($statement, $session->settings()->release());
        \MySqlMemory\Command\Program\ProgramSource::database($statement->name->schema, $session);
        if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger) {
            (new \MySqlMemory\Command\Program\RoutineCommand())->types($statement, $session, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, microtime(true)));
        }
        (new Reading())->read($operation, $session);
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($programs->parsed($diagnostic, false)) {
                throw (new \MySqlMemory\Session\Problem\Errors())->error($diagnostic, $session, 'field list', $statement);
            }
        }
        throw \MySqlMemory\Error\Family\StatementError::UnsupportedPreparedStatement->error();
    }
}
