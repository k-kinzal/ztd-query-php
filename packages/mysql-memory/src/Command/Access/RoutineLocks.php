<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access;

use MySqlMemory\Command\Program\ProgramSource;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\DropProgram;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Reports stored-routine failures under explicit table locks.
 *
 * CREATE and ALTER append their operation failure after the lock condition.
 * DROP also attempts to open the grant tables and records the failed automatic
 * privilege revocation. Legacy DROP appends its own failure when those tables
 * can be opened for writing. Observed on native MySQL 5.6 through 9.1.
 *
 * @visibility MySqlMemory
 */
final class RoutineLocks
{
    /**
     * Refuses creation before routine existence and binary-log safety checks.
     *
     * @throws SqlError When explicit table locks remain
     */
    public function create(CreateFunction|CreateProcedure $statement, Session $session): void
    {
        if ($session->locks !== []) {
            throw new SqlError(StatementError::LockedOrActiveTransaction, StatementError::LockedOrActiveTransaction->message(), following: [[ProgramError::RoutineCreateFailed->value, ProgramError::RoutineCreateFailed->message($statement instanceof CreateFunction ? 'FUNCTION' : 'PROCEDURE', $statement->name->name->value)]]);
        }
    }

    /**
     * Refuses alteration before looking up the routine.
     *
     * @throws SqlError When explicit table locks remain
     */
    public function alter(AlterRoutine $statement, Session $session): void
    {
        if ($session->locks !== []) {
            $name = ($session->settings()->legacy() ? ProgramSource::database($statement->name->schema, $session) . '.' : '') . $statement->name->name->value;
            throw new SqlError(StatementError::LockedOrActiveTransaction, StatementError::LockedOrActiveTransaction->message(), following: [[ProgramError::RoutineAlterFailed->value, ProgramError::RoutineAlterFailed->message($statement->kind->value, $name)]]);
        }
    }

    /**
     * Refuses a stored-routine drop and retains subsequent grant-table conditions.
     *
     * @throws SqlError When explicit table locks remain
     */
    public function drop(DropProgram $statement, Session $session): void
    {
        if ($session->locks === [] || !in_array($statement->kind, [ProgramKind::Function, ProgramKind::Procedure], true) || ($statement->kind === ProgramKind::Function && $statement->name->schema === null && $session->variables->database === '')) {
            return;
        }
        $database = ProgramSource::database($statement->name->schema, $session);
        $error = StatementError::LockedOrActiveTransaction;
        $session->diagnostics->error($error->value, $error->message());
        $failed = false;
        try {
            $tables = $session->settings()->legacy() ? AccountTables::LEGACY : [...AccountTables::LEGACY, ...AccountTables::MODERN];
            (new Locks())->required($session, array_map(static fn (string $table): QualifiedName => new QualifiedName(new Name($table), new Name('mysql')), $tables), true);
        } catch (SqlError $failure) {
            $session->diagnostics->error($failure->getCode(), $failure->getMessage());
            $failed = true;
        }
        if (!$failed && $session->settings()->legacy()) {
            $session->instance->accounts->forget($statement->kind->value, $database, $statement->name->name->value);
            $session->diagnostics->error(ProgramError::RoutineDropFailed->value, ProgramError::RoutineDropFailed->message($statement->kind->value, $database . '.' . $statement->name->name->value));
        } else {
            $session->diagnostics->warning(ProgramError::RoutineRevokeFailed, ProgramError::RoutineRevokeFailed->message());
        }
        throw new SqlError($error, $error->message(), recorded: true);
    }
}
