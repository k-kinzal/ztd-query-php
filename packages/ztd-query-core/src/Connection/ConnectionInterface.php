<?php

declare(strict_types=1);

namespace ZtdQuery\Connection;

/**
 * Minimal connection interface for ZTD layer.
 *
 * This interface defines the contract that all database adapters must implement
 * to work with the ZTD session. It provides a driver-agnostic API for executing
 * SQL queries.
 */
interface ConnectionInterface
{
    /**
     * Execute a query and return a statement.
     *
     * @return StatementInterface|false The statement on success, false on failure.
     */
    public function query(string $sql): StatementInterface|false;
}
