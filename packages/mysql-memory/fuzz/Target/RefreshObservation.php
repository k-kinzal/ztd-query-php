<?php

declare(strict_types=1);

namespace Fuzz\Target;

use mysqli;
use mysqli_result;
use RuntimeException;

/**
 * Observes the legacy refresh command independently on either wire endpoint.
 */
final class RefreshObservation
{
    /**
     * @param list<string> $beforeRefresh Statements to execute immediately before the wire command
     * @return array<string, mixed>
     * @throws RuntimeException When the endpoint or result does not follow the fixture contract
     */
    public static function observe(string $dsn, string $user, string $password, int $flags, array $beforeRefresh): array
    {
        if (preg_match('/host=([^;]+);port=(\d+)/', $dsn, $address) !== 1) {
            throw new RuntimeException('Expected a TCP MySQL DSN.');
        }
        $connection = new mysqli($address[1], $user, $password, '', (int) $address[2]);
        try {
            $connection->query('CREATE DATABASE IF NOT EXISTS refresh_command_test');
            $connection->select_db('refresh_command_test');
            $connection->query('CREATE TABLE IF NOT EXISTS t(id INT AUTO_INCREMENT PRIMARY KEY,a INT)');
            $connection->query('TRUNCATE t');
            $connection->query('FLUSH LOCAL STATUS');
            $connection->query('START TRANSACTION');
            $connection->query("DO 'a'+1");
            $before = self::rows($connection, "SHOW GLOBAL STATUS LIKE 'Flush_commands'")[0][1];
            $connection->query('INSERT INTO t(a) VALUES(1)');
            foreach ($beforeRefresh as $sql) {
                $connection->query($sql);
            }
            $ok = self::refresh($connection, $flags);
            $driver = [$connection->affected_rows, $connection->insert_id, $connection->info];
            $warnings = $connection->warning_count;
            $conditions = self::rows($connection, 'SHOW WARNINGS');
            $raised = self::rows($connection, 'SHOW COUNT(*) WARNINGS');
            $counts = self::rows($connection, "SHOW SESSION STATUS WHERE Variable_name IN ('Com_flush','Com_show_warnings','Questions')");
            $after = self::rows($connection, "SHOW GLOBAL STATUS LIKE 'Flush_commands'")[0][1];
            $connection->query('ROLLBACK');
            $rows = self::rows($connection, 'SELECT COUNT(*) FROM t');

            return ['ok' => $ok, 'driver' => $driver, 'warnings' => $warnings, 'conditions' => $conditions, 'raised' => $raised, 'counts' => $counts, 'flushDelta' => (int) $after - (int) $before, 'rows' => $rows];
        } finally {
            $connection->query('DROP DATABASE refresh_command_test');
            $connection->close();
        }
    }

    /**
     * Consumes only PHP's documented API deprecation; server diagnostics remain observable.
     */
    public static function refresh(mysqli $connection, int $flags): bool
    {
        set_error_handler(static fn (int $severity, string $message): bool => $severity === E_DEPRECATED && str_contains($message, 'mysqli::refresh()'));
        try {
            return $connection->refresh($flags);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @return list<list<string|null>>
     * @throws RuntimeException When the query does not return text-protocol rows
     */
    public static function rows(mysqli $connection, string $sql): array
    {
        $result = $connection->query($sql);
        if (!$result instanceof mysqli_result) {
            throw new RuntimeException('Expected a result set.');
        }

        $rows = [];
        foreach ($result->fetch_all(MYSQLI_NUM) as $row) {
            $values = [];
            foreach ($row as $value) {
                if (!is_string($value) && $value !== null) {
                    throw new RuntimeException('Expected a string or NULL from the text protocol.');
                }
                $values[] = $value;
            }
            $rows[] = $values;
        }

        return $rows;
    }
}
