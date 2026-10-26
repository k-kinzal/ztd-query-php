<?php

declare(strict_types=1);

namespace Fuzz\Observation;

use DateTimeImmutable;
use DateTimeZone;
use Fuzz\Fixture\Tables;
use PDO;
use RuntimeException;

/**
 * Validates the actual-clock creation and update times of the fresh fixture tables.
 *
 * SHOW TABLE STATUS reads each server's own commit clock, independently of SET timestamp.
 * Legacy creation times use the file clock, while modern creation times use SET timestamp.
 * Only times inside intervals sampled around the corresponding CREATE or INSERT are
 * interchangeable. MySQL 5.7 and the supported 8.x/9.x update times can precede the insertion sample by one
 * second, as observed on two independent instances; only that lower bound is widened.
 * MySQL 5.6's NULL update time stays exact. Other columns, metadata,
 * warnings and table contents stay exact. Each server must satisfy its own intervals.
 * SQL is never changed. This contract does not apply to arbitrary expressions or writes.
 */
final class TableTimes
{
    /**
     * The comparison contract recorded after every returned table's timestamp was validated.
     */
    public const CONTRACT = 'table-update-time-within-fixture-insert-interval';

    /**
     * The recorded rule for legacy table creation clocks.
     */
    public const CREATION = 'table-creation-time-within-fixture-create-interval';

    /**
     * The observed one-second lag of the MySQL 5.7 insertion clock.
     */
    public const LEGACY_UPDATE = 'legacy-table-update-time-within-fixture-insert-interval-or-previous-second';

    /**
     * The independently observed one-second lag of supported modern insertion clocks.
     */
    public const CACHED_UPDATE = 'table-update-time-within-fixture-insert-interval-or-previous-second';

    /**
     * @var array<string, array{string, string}> Each fixture table's insertion interval on this server
     */
    public array $intervals = [];

    /**
     * @var array<string, array{string, string}> Each table's creation interval on this server
     */
    public array $creations = [];

    /**
     * @param string $version The release whose table-status clock behavior is observed
     */
    public function __construct(public readonly string $version = '8.4.7')
    {
    }

    /**
     * Selects only a plain inspection of the fresh fixture, whose rows cannot change during the statement.
     */
    public static function handles(string $sql, string $version): bool
    {
        return in_array($version, ['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'], true) && preg_match('/\A\s*SHOW\s+TABLE\s+STATUS\s*;?\s*\z/i', $sql) === 1;
    }

    /**
     * Reads the server's actual clock in its current time zone without using the pinned statement clock.
     *
     * @throws RuntimeException When the server does not return a second-resolution SQL datetime
     */
    public function now(PDO $pdo, ?Record $record = null): string
    {
        $rows = (new Observer())->rows($pdo, 'SELECT SYSDATE()', $record);
        $value = count($rows) === 1 && is_array($rows[0]) && count($rows[0]) === 1 ? $rows[0][0] : null;
        if (!is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $value) !== 1) {
            throw new RuntimeException('Cannot sample the fixture commit clock.');
        }

        return $value;
    }

    /**
     * Executes fixture SQL, sampling immediately before and after each table's CREATE and INSERT.
     */
    public function execute(PDO $pdo, string $sql, ?Record $record = null): void
    {
        $table = preg_match('/\A(CREATE TABLE|INSERT INTO) (t1|t2|_sqlfaker_identifier)\b/', $sql, $match) === 1 ? $match[2] : null;
        $before = $table === null ? null : $this->now($pdo, $record);
        (new Tables())->execute($pdo, $sql);
        if ($table !== null && $before !== null) {
            $interval = [$before, $this->now($pdo, $record)];
            if ($match[1] === 'CREATE TABLE') {
                $this->creations[$table] = $interval;
            } else {
                $this->intervals[$table] = $interval;
            }
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
        if (!is_array($rows) || count($rows) !== count(Tables::TABLES) || !is_array($columns) || !is_array($columns[12] ?? null) || array_slice($columns[12], 0, 3) !== ['Update_time', 'TABLES', 'DATETIME']) {
            return $observation;
        }
        if (str_starts_with($this->version, '5.') && (!is_array($columns[11] ?? null) || array_slice($columns[11], 0, 3) !== ['Create_time', 'TABLES', 'DATETIME'])) {
            return $observation;
        }
        $seen = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row) || !is_string($row[0] ?? null) || isset($seen[$row[0]])) {
                return $observation;
            }
            $seen[$row[0]] = true;
            $row = $this->row($row);
            if ($row === null) {
                return $observation;
            }
            $rows[$index] = $row;
        }
        $result['rows'] = $rows;
        $observation['results'] = [$result];
        $observation['contracts'] = [...(str_starts_with($this->version, '5.') ? [self::CREATION] : []), ...(str_starts_with($this->version, '5.6.') ? [] : [$this->updateContract()])];

        return $observation;
    }

    /**
     * @param array<array-key, mixed> $row One complete table-status row
     * @return array<array-key, mixed>|null The comparison row, or null when a clock condition fails
     */
    public function row(array $row): ?array
    {
        $table = $row[0] ?? null;
        if (!is_string($table)) {
            return null;
        }
        if (str_starts_with($this->version, '5.')) {
            if (!is_string($row[11] ?? null) || !$this->contains($table, $row[11], true)) {
                return null;
            }
            $row[11] = '{' . self::CREATION . '}';
        }
        if (str_starts_with($this->version, '5.6.')) {
            return array_key_exists(12, $row) && $row[12] === null ? $row : null;
        }
        if (!is_string($row[12] ?? null) || !$this->contains($table, $row[12])) {
            return null;
        }
        $row[12] = '{' . ($this->updateContract()) . '}';

        return $row;
    }

    /**
     * Tells whether a valid SQL datetime belongs to the sampled insertion interval of a table.
     */
    public function contains(string $table, string $value, bool $creation = false): bool
    {
        $interval = ($creation ? $this->creations : $this->intervals)[$table] ?? null;
        if ($interval === null || $interval[0] > $interval[1] || $value > $interval[1] || preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $value) !== 1) {
            return false;
        }
        $lower = $interval[0];
        if (!$creation && $this->updateContract() !== self::CONTRACT) {
            $before = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $lower, new DateTimeZone('UTC'));
            if ($before === false || $before->format('Y-m-d H:i:s') !== $lower) {
                return false;
            }
            $lower = $before->modify('-1 second')->format('Y-m-d H:i:s');
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

        return $value >= $lower && $parsed !== false && $parsed->format('Y-m-d H:i:s') === $value;
    }
    /**
     * Selects only the clock lag independently observed on each named release.
     */
    public function updateContract(): string
    {
        return match ($this->version) {
            '5.7.44' => self::LEGACY_UPDATE,
            '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0' => self::CACHED_UPDATE,
            default => self::CONTRACT,
        };
    }

}
