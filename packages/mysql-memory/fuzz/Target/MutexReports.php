<?php

declare(strict_types=1);

namespace Fuzz\Target;

/**
 * Validates physical InnoDB latch counters, which memory reports as an empty set.
 *
 * Background engine threads can produce waits after a counter reset. The rows
 * describe physical mutexes and rwlocks rather than SQL-history counters.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-engine.html.
 */
final class MutexReports
{
    /**
     * Names the explicit comparison contract for unavailable physical latches.
     */
    public const CONTRACT = 'physical-innodb-mutex-empty-memory-report';

    /**
     * Selects a single read of InnoDB's physical latch report.
     */
    public static function handles(string $sql): bool
    {
        return preg_match('/\A\s*SHOW\s+ENGINE\s+(?:ALL|INNODB)\s+MUTEX\s*;?\s*\z/i', $sql) === 1;
    }

    /**
     * Accepts empty native reports or named InnoDB latches with nonnegative counters.
     *
     * @param array<mixed> $rows Native diagnostic rows
     */
    public function native(array $rows): bool
    {
        foreach ($rows as $row) {
            if (!is_array($row) || count($row) !== 3 || ($row[0] ?? null) !== 'InnoDB' || !is_string($row[1] ?? null) || $row[1] === '' || str_contains($row[1], "\0") || !is_string($row[2] ?? null)) {
                return false;
            }
            $fields = preg_split('/[ ,]+/', $row[2]);
            if ($fields === false || $fields === []) {
                return false;
            }
            $seen = [];
            foreach ($fields as $field) {
                if (preg_match('/\A(spins|waits|calls|count|spin_waits|spin_rounds|os_waits|os_yields)=[0-9]+\z/', $field, $match) !== 1 || isset($seen[$match[1]])) {
                    return false;
                }
                $seen[$match[1]] = true;
            }
        }

        return true;
    }
}
