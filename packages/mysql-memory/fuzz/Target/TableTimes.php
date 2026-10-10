<?php

declare(strict_types=1);

namespace Fuzz\Target;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

/**
 * Validates the actual-clock update times of the freshly inserted fixture tables.
 *
 * SHOW TABLE STATUS reads each server's own commit clock, independently of SET timestamp.
 * For the plain read on modern releases, only an Update_time inside the interval sampled
 * around that table's INSERT is interchangeable. NULL, the pinned statement clock, a time
 * outside that interval, other columns, metadata, warnings and table contents stay exact.
 * SQL is never changed. This contract does not apply to arbitrary expressions or writes.
 */
final class TableTimes
{
    /**
     * The comparison contract recorded after every returned table's timestamp was validated.
     */
    public const CONTRACT = 'table-update-time-within-fixture-insert-interval';

    /**
     * @var array<string, array{string, string}> Each fixture table's insertion interval on this server
     */
    public array $intervals = [];

    /**
     * Selects only a plain inspection of the fresh fixture, whose rows cannot change during the statement.
     */
    public static function handles(string $sql, string $version): bool
    {
        return !str_starts_with($version, '5.') && preg_match('/\A\s*SHOW\s+TABLE\s+STATUS\s*;?\s*\z/i', $sql) === 1;
    }

    /**
     * Reads the server's actual clock in its current time zone without using the pinned statement clock.
     *
     * @throws RuntimeException When the server does not return a second-resolution SQL datetime
     */
    public function now(PDO $pdo): string
    {
        $statement = $pdo->query('SELECT SYSDATE()');
        $value = $statement === false ? false : $statement->fetchColumn();
        if (!is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $value) !== 1) {
            throw new RuntimeException('Cannot sample the fixture commit clock.');
        }

        return $value;
    }

    /**
     * Executes a fixture statement, sampling immediately before and after each table's INSERT.
     */
    public function execute(PDO $pdo, string $sql): void
    {
        $table = preg_match('/\AINSERT INTO (t1|t2)\b/', $sql, $match) === 1 ? $match[1] : null;
        $before = $table === null ? null : $this->now($pdo);
        $pdo->exec($sql);
        if ($table !== null && $before !== null) {
            $this->intervals[$table] = [$before, $this->now($pdo)];
        }
    }

    /**
     * @param array<string, mixed> $observation The complete raw observation
     * @return array<string, mixed> A copy with independently validated commit instants represented by their contract
     */
    public function comparable(array $observation): array
    {
        $results = $observation['results'] ?? null;
        if (!is_array($results) || count($results) !== 1 || !is_array($results[0] ?? null)) {
            return $observation;
        }
        $result = $results[0];
        $rows = $result['rows'] ?? null;
        $columns = $result['columns'] ?? null;
        if (!is_array($rows) || count($rows) !== 2 || !is_array($columns) || !is_array($columns[12] ?? null) || array_slice($columns[12], 0, 3) !== ['Update_time', 'TABLES', 'DATETIME']) {
            return $observation;
        }
        $seen = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row) || !is_string($row[0] ?? null) || !is_string($row[12] ?? null)) {
                return $observation;
            }
            if (isset($seen[$row[0]]) || !$this->contains($row[0], $row[12])) {
                return $observation;
            }
            $seen[$row[0]] = true;
            $row[12] = '{' . self::CONTRACT . '}';
            $rows[$index] = $row;
        }
        $result['rows'] = $rows;
        $observation['results'] = [$result];
        $observation['contracts'] = [self::CONTRACT];

        return $observation;
    }

    /**
     * Tells whether a valid SQL datetime belongs to the sampled insertion interval of a table.
     */
    public function contains(string $table, string $value): bool
    {
        $interval = $this->intervals[$table] ?? null;
        if ($interval === null || $interval[0] > $interval[1] || $value < $interval[0] || $value > $interval[1] || preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $value) !== 1) {
            return false;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

        return $parsed !== false && $parsed->format('Y-m-d H:i:s') === $value;
    }
}
