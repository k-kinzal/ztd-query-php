<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Storage\Heap;

/**
 * The transaction of a session, and the atomicity of each statement.
 *
 * Before a statement first changes a table, the table's rows are kept; a statement that fails
 * restores them, as InnoDB rolls back a failed statement. Inside an explicit transaction the
 * rows kept at its first change are restored by ROLLBACK.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-autocommit-commit-rollback.html.
 *
 * @visibility MySqlMemory
 */
final class Transaction
{
    /**
     * Whether an explicit transaction is open.
     */
    public bool $open = false;

    /**
     * @var array<int, array{StoredTable, Heap}> The rows of each table before the current statement changed it
     */
    private array $statement = [];

    /**
     * @var array<int, array{StoredTable, Heap}> The rows of each table before the open transaction changed it
     */
    private array $kept = [];

    /**
     * @param Dictionary $dictionary The databases of the server
     */
    public function __construct(public readonly Dictionary $dictionary)
    {
    }

    /**
     * Keeps the rows of a table before a statement changes it.
     */
    public function touch(StoredTable $table): void
    {
        $id = spl_object_id($table);
        $this->statement[$id] ??= [$table, $table->data->copy()];
        if ($this->open) {
            $this->kept[$id] ??= [$table, $table->data->copy()];
        }
    }

    /**
     * Starts a statement.
     */
    public function beginStatement(): void
    {
        $this->statement = [];
    }

    /**
     * Ends a statement that succeeded.
     */
    public function endStatement(): void
    {
        $this->statement = [];
    }

    /**
     * Restores the tables a failed statement changed.
     */
    public function abortStatement(): void
    {
        foreach ($this->statement as [$table, $data]) {
            $table->data = $data;
        }
        $this->statement = [];
    }

    /**
     * Opens an explicit transaction, committing one that is open.
     */
    public function begin(): void
    {
        $this->kept = [];
        $this->open = true;
    }

    /**
     * Ends the open transaction, keeping its changes.
     */
    public function commit(): void
    {
        $this->kept = [];
        $this->open = false;
    }

    /**
     * Ends the open transaction, restoring the rows its changes replaced.
     */
    public function rollback(): void
    {
        foreach ($this->kept as [$table, $data]) {
            $table->data = $data;
        }
        $this->kept = [];
        $this->open = false;
    }
}
