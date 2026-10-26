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
    public function connection(PDO $owner, ?Record $record = null): int
    {
        $rows = (new Observer())->rows($owner, 'SELECT CONNECTION_ID()', $record);
        if (count($rows) !== 1 || !is_array($rows[0]) || !is_int($rows[0][0] ?? null) || $rows[0][0] < 1) {
            throw new RuntimeException('Cannot identify the observed connection.');
        }
        return $rows[0][0];
    }

    /**
     * Requires exactly one live owner before an empty state inventory can be trusted.
     *
     * @phpstan-impure
     * @return positive-int
     * @throws RuntimeException When the connection has no unique owning thread
     */
    public function resolve(PDO $administrator, int $connection, ?Record $record = null): int
    {
        $threads = (new Observer())->rows($administrator, 'SELECT THREAD_ID FROM performance_schema.threads WHERE PROCESSLIST_ID = ' . $connection, $record);
        if (count($threads) !== 1 || !is_array($threads[0]) || !is_int($threads[0][0] ?? null) || $threads[0][0] < 1) {
            throw new RuntimeException('Cannot identify exactly one observed owning thread.');
        }
        return $threads[0][0];
    }
}
