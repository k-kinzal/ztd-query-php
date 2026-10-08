<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Routine\DropProgram;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Statement\Operation;

/**
 * Executes DROP PROCEDURE, DROP FUNCTION, DROP TRIGGER and DROP EVENT.
 *
 * A missing program is an error, or with IF EXISTS a note of the same condition, except a
 * missing event, which is ER_EVENT_DOES_NOT_EXIST but the note ER_SP_DOES_NOT_EXIST. A routine
 * is named with its database, which need not exist; an unqualified function with no database
 * selected names a loadable function. A trigger in a database that does not exist is
 * ER_BAD_DB_ERROR, and its note keeps the format of the message unfilled (verified on a live 8.4
 * server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-procedure.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-trigger.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-event.html.
 *
 * @visibility MySqlMemory
 */
final class DropProgramCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Drops the program.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof DropProgram);
        $session->transaction->commit();
        $missing = $this->drop($statement, $session);
        if ($missing === null) {
            return new Completion();
        }
        [$error, $note] = $missing;
        if (!$statement->ifExists) {
            throw $error;
        }
        $session->diagnostics->note($note->error, $note->getMessage());

        return new Completion(0, 0, $session->diagnostics->count());
    }

    /**
     * Drops the program, or answers the error and the note of a missing one.
     *
     * @return array{SqlError, SqlError}|null
     *
     * @throws SqlError When no database is selected for an unqualified name
     */
    public function drop(DropProgram $statement, Session $session): ?array
    {
        $name = $statement->name->name->value;
        $key = strtolower($name);
        if ($statement->kind === ProgramKind::Function && $statement->name->schema === null && $session->variables->database === '') {
            $error = ErrorCode::RoutineMissing->error('FUNCTION (UDF)', $name);

            return [$error, $error];
        }
        $database = ProgramSource::database($statement->name->schema, $session);
        $schema = $session->instance->dictionary->schema($database);
        switch ($statement->kind) {
            case ProgramKind::Procedure:
            case ProgramKind::Function:
                $function = $statement->kind === ProgramKind::Function;
                if ($schema === null || !isset(RoutineCommand::routines($schema, $function)[$key])) {
                    $error = ErrorCode::RoutineMissing->error($statement->kind->value, $database . '.' . $name);

                    return [$error, $error];
                }
                if ($function) {
                    unset($schema->functions[$key]);
                } else {
                    unset($schema->procedures[$key]);
                }
                $session->instance->accounts->forget($function ? 'FUNCTION' : 'PROCEDURE', $database, $name);

                return null;
            case ProgramKind::Trigger:
                if ($schema === null) {
                    return [ErrorCode::BadDatabase->error($database), new SqlError(ErrorCode::BadDatabase, "Unknown database '%-.192s'")];
                }
                foreach ($schema->triggers as $position => $trigger) {
                    if (strtolower($trigger->name) === $key) {
                        array_splice($schema->triggers, $position, 1);

                        return null;
                    }
                }
                $error = ErrorCode::TriggerMissing->error();

                return [$error, $error];
            case ProgramKind::Event:
                if ($schema === null || !isset($schema->events[$key])) {
                    return [ErrorCode::EventMissing->error($name), ErrorCode::RoutineMissing->error('Event', $name)];
                }
                unset($schema->events[$key]);

                return null;
        }
    }
}
