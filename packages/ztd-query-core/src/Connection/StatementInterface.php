<?php

declare(strict_types=1);

namespace ZtdQuery\Connection;

/**
 * Minimal statement interface for ZTD layer.
 *
 * This interface defines the contract that all statement adapters must implement
 * to work with the ZTD session. It provides a driver-agnostic API for executing
 * prepared statements and fetching results.
 */
interface StatementInterface
{
    /**
     * Execute the prepared statement.
     *
     * @param array<int|string, mixed>|null $params Optional parameters to bind.
     * @return bool True on success, false on failure.
     */
    public function execute(?array $params = null): bool;

    /**
     * Fetch all rows as associative arrays.
     *
     * @return array<int, array<string, mixed>> Array of associative arrays.
     */
    public function fetchAll(): array;

    /**
     * Return the number of affected rows.
     */
    public function rowCount(): int;
}
