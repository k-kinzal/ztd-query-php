<?php

declare(strict_types=1);

namespace Fuzz\Observation;

use PDO;
use RuntimeException;

/**
 * Resolves a live session through public server identities, independently of SQL Semantics.
 */
final class OwnerThread
{
    /**
     * Captures the connection identity before the tested statement or its diagnostics.
     *
     * @phpstan-impure
     * @return positive-int
     * @throws RuntimeException When the connection identity cannot be read
     */
    public function connection(PDO $owner): int
    {
        $statement = $owner->query('SELECT CONNECTION_ID()');
        $connection = $statement === false ? false : $statement->fetchColumn();
        if (!is_int($connection) || $connection < 1) {
            throw new RuntimeException('Cannot identify the observed connection.');
        }
        return $connection;
    }

    /**
     * Requires exactly one live owner before an empty state inventory can be trusted.
     *
     * @phpstan-impure
     * @return positive-int
     * @throws RuntimeException When the connection has no unique owning thread
     */
    public function resolve(PDO $administrator, int $connection): int
    {
        $statement = $administrator->query('SELECT THREAD_ID FROM performance_schema.threads WHERE PROCESSLIST_ID = ' . $connection);
        $threads = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_COLUMN);
        if (count($threads) !== 1 || !is_int($threads[0]) || $threads[0] < 1) {
            throw new RuntimeException('Cannot identify exactly one observed owning thread.');
        }
        return $threads[0];
    }
}
