<?php

declare(strict_types=1);

namespace MySqlMemory\Concurrency;

use MySqlMemory\Error\Family\TransactionError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement as MySql;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Statement;

/**
 * Refuses the statements a read-only transaction or session cannot run (ER_CANT_EXECUTE_IN_READ_ONLY_TRANSACTION).
 *
 * A statement that runs in a read-only transaction cannot write a table that is not a temporary
 * table of the session, nor create or drop a temporary table; a table that does not exist counts
 * as one that is not temporary. A statement that commits implicitly ends the transaction first,
 * so it is refused only when the session itself is read-only. The check comes before the tables
 * are opened, so it is reported before an unknown table. A transaction is read-only when START
 * TRANSACTION READ ONLY started it, when SET TRANSACTION READ ONLY was its next transaction, or
 * when the session is read-only (verified on a live 8.4 server). SELECT ... FOR UPDATE is refused
 * where it locks a table ({@see \MySqlMemory\Plan\Locking}).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-transaction.html#set-transaction-access-mode.
 *
 * @visibility MySqlMemory
 */
final class Access
{
    /**
     * The statements that commit implicitly and change data or definitions, which a read-only session cannot run.
     */
    public const DEFINITIONS = [
        MySql\Table\CreateTable::class, MySql\Table\CreateTableLike::class, MySql\Alter\DropTable::class, MySql\Alter\TruncateTable::class,
        MySql\Alter\AlterTable::class, MySql\Table\CreateIndex::class, MySql\Alter\DropIndex::class, MySql\Alter\RenameTable::class,
        MySql\Server\Database\CreateDatabase::class, MySql\Server\Database\DropDatabase::class, MySql\Server\Database\AlterDatabase::class,
        MySql\View\CreateView::class, MySql\View\AlterView::class, MySql\View\DropView::class,
        MySql\Routine\CreateProcedure::class, MySql\Routine\CreateFunction::class, MySql\Routine\AlterRoutine::class, MySql\Routine\CreateTrigger::class,
        MySql\Routine\CreateEvent::class, MySql\Routine\AlterEvent::class, MySql\Routine\DropProgram::class,
        MySql\Account\CreateUser::class, MySql\Account\CreateRole::class, MySql\Account\DropUser::class, MySql\Account\DropRole::class,
        MySql\Account\AlterUser::class, MySql\Account\RenameUser::class, MySql\Account\SetPassword::class,
        MySql\Account\Privilege\GrantPrivileges::class, MySql\Account\Privilege\GrantRoles::class, MySql\Account\Privilege\GrantProxy::class,
        MySql\Account\Privilege\RevokePrivileges::class, MySql\Account\Privilege\RevokeRoles::class, MySql\Account\Privilege\RevokeProxy::class,
        MySql\Account\Privilege\RevokeAll::class, MySql\Server\Maintenance\OptimizeTable::class, MySql\Server\Maintenance\RepairTable::class,
    ];

    /**
     * Refuses a statement the read-only transaction or session it runs in cannot run.
     *
     * @throws SqlError When the statement is refused
     */
    public function check(Statement $statement, Session $session): void
    {
        $transaction = $session->transaction;
        $temporary = ($statement instanceof MySql\Table\CreateTable && $statement->temporary())
            || ($statement instanceof MySql\Table\CreateTableLike && $statement->temporaryWords > 0)
            || ($statement instanceof MySql\Alter\DropTable && $statement->temporary);
        $readOnly = $transaction->open ? $transaction->readOnly : ($transaction->nextReadOnly ?? $transaction->sessionReadOnly());
        if ($temporary) {
            if ($readOnly) {
                throw TransactionError::ReadOnlyTransaction->error();
            }

            return;
        }
        if (in_array($statement::class, self::DEFINITIONS, true) || $this->writesLocked($statement)) {
            if ($transaction->sessionReadOnly()) {
                throw TransactionError::ReadOnlyTransaction->error();
            }

            return;
        }
        if (!$readOnly) {
            return;
        }
        foreach ($this->written($statement) as $name) {
            $table = $name === null ? null : $session->instance->dictionary->table($name->schema->value ?? $session->variables->database, $name->name->value);
            if ($table === null || !$table->definition->temporary) {
                throw TransactionError::ReadOnlyTransaction->error();
            }
        }
    }

    /**
     * Tells whether a statement is LOCK TABLES with a WRITE lock.
     */
    public function writesLocked(Statement $statement): bool
    {
        if (!$statement instanceof MySql\Server\Lock\LockTables) {
            return false;
        }
        foreach ($statement->locks as $lock) {
            if ($lock->mode === MySql\Server\Lock\LockMode::Write || $lock->mode === MySql\Server\Lock\LockMode::LowPriorityWrite) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the names of the tables a statement writes rows of, null for one it writes through a join.
     *
     * @return list<QualifiedName|null>
     */
    public function written(Statement $statement): array
    {
        return match (true) {
            $statement instanceof MySql\Dml\Insert\InsertRows, $statement instanceof MySql\Dml\Insert\InsertSet, $statement instanceof MySql\Dml\Insert\InsertQuery => [$statement->into->table->name],
            $statement instanceof MySql\Dml\Delete => [$statement->table->name],
            $statement instanceof MySql\Dml\MultipleDelete => $statement->targets,
            $statement instanceof MySql\Dml\Update => array_map(static fn ($relation): ?QualifiedName => $relation instanceof MySql\Relation\TableReference ? $relation->name : null, $statement->tables),
            $statement instanceof MySql\Dml\Load\LoadTable => [null],
            default => [],
        };
    }
}
