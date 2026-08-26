<?php

declare(strict_types=1);

namespace ZtdQuery\Connection;

use ZtdQuery\Platform\ResultColumnTypeResolver;

/**
 * Minimal statement interface for ZTD layer.
 *
 * This interface defines the contract that all statement adapters must implement
 * to work with the ZTD session. It provides a driver-agnostic API for executing
 * prepared statements and fetching results.
 *
 * A column of a row is a scalar or null. That is what every driver behind this
 * interface hands back and what every driver will take on the way in, so it is
 * declared here, at the edge, and the code inside works with the narrowed type
 * rather than guessing at each step what a row might be holding.
 *
 * @phpstan-type RowValue int|float|string|bool|null
 * @phpstan-type Row array<string, RowValue>
 */
interface StatementInterface
{
    /**
     * Execute the prepared statement.
     *
     * @param array<int|string, RowValue>|null $params Optional parameters to bind.
     * @return bool True on success, false on failure.
     */
    public function execute(?array $params = null): bool;

    /**
     * Fetch all rows as associative arrays.
     *
     * @return list<Row> Array of associative arrays.
     */
    public function fetchAll(): array;

    /**
     * Return result-set columns in projection order.
     *
     * Metadata must remain available when the result contains no rows.
     *
     * @return list<ResultColumn>
     */
    public function resultColumns(ResultColumnTypeResolver $typeResolver): array;

    /**
     * Return the number of affected rows.
     */
    public function rowCount(): int;
}
