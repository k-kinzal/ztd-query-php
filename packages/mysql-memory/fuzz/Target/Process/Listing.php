<?php

declare(strict_types=1);

namespace Fuzz\Target\Process;

use Fuzz\Target\Observer;
use PDO;
use RuntimeException;

/**
 * Validates the allocated identities, peer ports and elapsed times of a complete process list.
 *
 * Each server is bracketed independently with process-table reads. The current and repair
 * connections identify themselves with CONNECTION_ID(); an enabled scheduler must contribute
 * exactly one additional daemon. Client ports must be valid and unchanged in both samples.
 * Current-query time must follow SYSDATE minus the pinned timestamp; idle and daemon times
 * must lie between their sampled values. Only these validated fields become interchangeable.
 * All columns, rows, users, host names, databases, commands, states, query text and warnings
 * remain in the comparison. Missing, additional, duplicated or malformed rows stay raw.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-processlist.html.
 *
 * @phpstan-import-type ProcessRow from Sample
 */
final class Listing
{
    /**
     * The independently checked identity, port and clock contract.
     */
    public const CONTRACT = 'process-identities-and-ports-stable-between-samples-with-bounded-time';

    /**
     * @param Sample $before The independent sample preceding the listing
     * @param int $guard The repair connection's independently sampled identity
     */
    public function __construct(public readonly Sample $before, public readonly int $guard)
    {
    }

    /**
     * Selects only one complete process listing, including the FULL form.
     */
    public static function handles(string $sql): bool
    {
        return preg_match('/\A\s*SHOW\s+(?:FULL\s+)?PROCESSLIST\s*;?\s*\z/i', $sql) === 1;
    }

    /**
     * Restarts an enabled scheduler after replacing the fixture database, on both servers.
     *
     * Dropping an event does not wake a sleeping daemon. Resetting its queue here prevents a
     * previous input's abandoned wait from leaking into this isolated listing. The setting
     * remains unchanged, and the generated statement still lists every row and field.
     *
     * @throws RuntimeException When the server refuses the fixture's scheduler setup
     */
    public static function prepare(PDO $pdo): void
    {
        $setting = $pdo->query('SELECT @@GLOBAL.event_scheduler');
        if ($setting === false || $setting->fetchColumn() !== 'ON') {
            return;
        }
        foreach (['SET GLOBAL event_scheduler=OFF', 'DO SLEEP(0.1)', 'SET GLOBAL event_scheduler=ON', 'DO SLEEP(0.1)'] as $sql) {
            if ($pdo->exec($sql) === false) {
                throw new RuntimeException('Cannot prepare the event daemon for an isolated process listing.');
            }
        }
    }

    /**
     * Captures the two connection identities and the initial full process snapshot.
     */
    public static function capture(PDO $pdo, PDO $guard): ?self
    {
        $statement = $guard->query('SELECT CONNECTION_ID()');
        $identity = $statement === false ? false : $statement->fetchColumn();
        $before = Sample::read($pdo);

        return is_int($identity) && $before !== null ? new self($before, $identity) : null;
    }

    /**
     * Samples after the observation and retains raw output when any validation fails.
     *
     * @param array<string, mixed> $observation
     * @return array<string, mixed>
     */
    public function comparable(PDO $pdo, array $observation): array
    {
        $after = Sample::read($pdo);

        return $after === null ? $observation : $this->validated($observation, $after);
    }

    /**
     * Validates the complete shape and all rows before substituting any field.
     *
     * @param array<string, mixed> $observation
     * @return array<string, mixed>
     */
    public function validated(array $observation, Sample $after): array
    {
        $results = $observation['results'] ?? null;
        if (isset($observation['error']) || !is_array($results) || count($results) !== 1 || !is_array($results[0] ?? null)) {
            return $observation;
        }
        $columns = $results[0]['columns'] ?? null;
        $rows = $results[0]['rows'] ?? null;
        if (!is_array($columns) || array_column($columns, 0) !== ['Id', 'User', 'Host', 'db', 'Command', 'Time', 'State', 'Info'] || !is_array($rows)) {
            return $observation;
        }
        $indexed = Sample::indexed($rows);
        $comparable = $indexed === null ? null : $this->rows($indexed, $after);
        if ($comparable === null) {
            return $observation;
        }
        $results[0]['rows'] = (new Observer())->bag($comparable);
        $observation['results'] = $results;
        $observation['contracts'] = [self::CONTRACT];

        return $observation;
    }

    /**
     * Preserves all rows and replaces only fields with a checked counterpart on this server.
     *
     * @param array<int, ProcessRow> $rows
     * @return list<list<int|string|null>>|null
     */
    public function rows(array $rows, Sample $after): ?array
    {
        $before = $this->before;
        $expected = $before->scheduler === 'ON' ? 3 : 2;
        if ($before->current !== $after->current || $before->current === $this->guard || $before->scheduler !== $after->scheduler || count($rows) !== $expected || array_keys($rows) !== array_keys($before->rows) || array_keys($rows) !== array_keys($after->rows) || !isset($rows[$before->current], $rows[$this->guard])) {
            return null;
        }
        $result = [];
        foreach ($rows as $id => $row) {
            $role = $id === $before->current ? 'current' : ($id === $this->guard ? 'guard' : 'daemon');
            $first = $before->rows[$id];
            $last = $after->rows[$id];
            $lower = $role === 'current' ? $before->age : $first[5];
            $upper = $role === 'current' ? $after->age : $last[5];
            $host = $this->host($row, $first, $last, $role);
            if ($host === null || $row[5] < $lower || $row[5] > $upper || ($role !== 'current' && $row[5] < 0) || ($role === 'daemon' && ($row[1] !== 'event_scheduler' || $row[4] !== 'Daemon'))) {
                return null;
            }
            $result[] = ['{' . self::CONTRACT . ':' . $role . '}', $row[1], $host, $row[3], $row[4], '{' . self::CONTRACT . ':elapsed}', $row[6], $row[7]];
        }

        return $result;
    }

    /**
     * Retains the host name and substitutes a valid, independently stable TCP source port.
     *
     * @param ProcessRow $row
     * @param ProcessRow $first
     * @param ProcessRow $last
     */
    public function host(array $row, array $first, array $last, string $role): ?string
    {
        if ($row[2] !== $first[2] || $row[2] !== $last[2]) {
            return null;
        }
        if ($role === 'daemon') {
            return $row[2] === 'localhost' ? $row[2] : null;
        }
        if (preg_match('/\A(.+):([1-9][0-9]*)\z/', $row[2], $parts) !== 1 || (int) $parts[2] > 65535) {
            return null;
        }

        return $parts[1] . ':{' . self::CONTRACT . ':port}';
    }
}
