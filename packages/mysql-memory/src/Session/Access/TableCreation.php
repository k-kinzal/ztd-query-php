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
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction;
use SqlSemantics\Statement\Statement;

/**
 * Validates CREATE TABLE ... START TRANSACTION and its restriction on following commands.
 *
 * These checks follow parse-time diagnostics and precede table and column resolution.
 * Only BINLOG, COMMIT and ROLLBACK may run until the transaction ends. Verified on MySQL 8.4.7.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility MySqlMemory
 */
final class TableCreation
{
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
        if (!$statement instanceof CreateTable || !$this->requested($statement)) {
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
