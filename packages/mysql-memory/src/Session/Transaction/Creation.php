<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Transaction;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Session\Transaction;

/**
 * Holds the table of CREATE TABLE ... START TRANSACTION until the transaction commits.
 *
 * ROLLBACK and disconnect discard the unpublished table. IF NOT EXISTS starts the same
 * restricted transaction without replacing an existing table. Verified on MySQL 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 *
 * @visibility MySqlMemory
 */
final class Creation
{
    /**
     * Whether CREATE TABLE ... START TRANSACTION restricts the following statements.
     */
    public bool $active = false;

    /**
     * The new table to publish, or null when IF NOT EXISTS kept the existing table.
     */
    public ?StoredTable $table = null;

    /**
     * @param Transaction $transaction The transaction that owns the unpublished table
     */
    public function __construct(public readonly Transaction $transaction)
    {
    }

    /**
     * Starts the restricted transaction, optionally holding a newly created table.
     */
    public function begin(?StoredTable $table = null): void
    {
        $this->transaction->begin();
        $this->table = $table;
        $this->active = true;
    }

    /**
     * Publishes the table when the transaction commits and ends the restriction.
     */
    public function commit(): void
    {
        if ($this->table !== null) {
            $this->transaction->dictionary->store($this->table);
        }
        $this->rollback();
    }

    /**
     * Discards the unpublished definition and ends the restriction.
     */
    public function rollback(): void
    {
        $this->table = null;
        $this->active = false;
    }
}
