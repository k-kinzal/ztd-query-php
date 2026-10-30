<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Command\Definition\StorageOptions;
use MySqlMemory\Error\Family\TransactionError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\BinlogEvent;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Commit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Rollback;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction;
use SqlSemantics\Statement\Statement;

/**
 * Checks table-creation state and CREATE TABLE ... START TRANSACTION restrictions.
 *
 * The legacy default-database check precedes definition diagnostics. Transaction
 * checks follow those diagnostics and precede table and column resolution.
 * Only BINLOG, COMMIT and ROLLBACK may run until the transaction ends. Verified on MySQL 8.4.7.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility MySqlMemory
 */
final class TableCreation
{
    /**
     * Opens a permanent creation target under its own explicit lock before its definition.
     * A READ lock suffices because an existing destination cannot be created again.
     * Temporary creation is exempt; an existing temporary table does not satisfy the
     * lock of a permanent creation with the same name. Verified on MySQL 5.6, 5.7 and 8.4.
     *
     * @throws SqlError When the permanent target has no matching explicit lock
     */
    public function locks(Statement $statement, Session $session): void
    {
        if ((!$statement instanceof CreateTable && !$statement instanceof CreateTableLike) || $statement->temporaryWords > 0 || $session->locks === []) {
            return;
        }
        $name = $statement->name;
        $schema = $name->schema->value ?? $session->variables->database;
        foreach ($session->locks as [$lockedSchema, $table, $alias]) {
            if ($schema === $lockedSchema && $name->name->value === $table && strcasecmp($alias, $table) === 0) {
                return;
            }
        }
        throw \MySqlMemory\Error\Family\StatementError::TableNotLocked->error($name->name->value);
    }

    /**
     * Checks the legacy default database before conditions from later definition clauses.
     *
     * MySQL 5.6 and 5.7 resolve an unqualified CREATE TABLE or ALTER TABLE target before reading its
     * options and query. Modern releases report those conditions first. Observed on
     * isolated 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers.
     *
     * @throws SqlError When the legacy table target has no default database
     * @param bool $contextualizing Whether grammar interpretation has finished and a table action is being contextualized
     */
    public function database(Statement $statement, Session $session, bool $contextualizing = false): void
    {
        $name = match (true) {
            $statement instanceof CreateTable => $statement->name,
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable => $statement->table,
            default => null,
        };
        if (($session->settings()->legacy() || $contextualizing) && $name !== null && $name->schema === null && $session->variables->database === '') {
            if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable && $statement->dot === \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written && $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744) {
                $notice = \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::DotTable;
                $session->diagnostics->warning($notice->code(), $notice->value);
            }
            throw \MySqlMemory\Error\Family\QueryError::NoDatabase->error();
        }
    }

    /**
     * Refuses statements outside the restricted transaction's command set.
     *
     * @throws SqlError When CREATE TABLE ... START TRANSACTION restricts the statement
     */
    public function check(Statement $statement, Session $session): void
    {
        if ($session->transaction->creation->active && !$statement instanceof BinlogEvent && !$statement instanceof Commit && !$statement instanceof Rollback) {
            throw TransactionError::CreateTransactionRestricted->error();
        }
    }

    /**
     * Tells whether a table definition requests a transaction that keeps the new table unpublished.
     */
    public function requested(CreateTable $create): bool
    {
        return array_filter($create->options, static fn ($option): bool => $option instanceof StartTransaction) !== [];
    }

    /**
     * Checks the engine and forbidden combinations before preparing the table or its query.
     *
     * Engine names and substitution warnings have already been resolved by StorageOptions.
     *
     * @throws SqlError When the table cannot be created in a restricted transaction
     */
    public function validate(Statement $statement, Session $session): void
    {
        if ((!$statement instanceof CreateTable) || !$this->requested($statement)) {
            return;
        }
        $engine = (new StorageOptions())->engine($statement, $session->variables);
        $reason = match (true) {
            $statement->query !== null => 'with CREATE TABLE ... AS SELECT statement.',
            strcasecmp($engine, 'InnoDB') !== 0 => 'with engine that does not support atomic DDL.',
            $statement->temporary() => 'to create temporary tables.',
            default => null,
        };
        if ($reason !== null) {
            throw TransactionError::CreateTransactionInvalid->error($reason);
        }
        foreach ($statement->elements as $element) {
            if ($element instanceof ForeignKey) {
                throw TransactionError::CreateTransactionForeignKey->error();
            }
        }
    }
}
