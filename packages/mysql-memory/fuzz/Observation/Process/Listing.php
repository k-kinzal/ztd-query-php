<?php

declare(strict_types=1);

namespace Fuzz\Observation\Process;

use Fuzz\Observation\Observer;
use PDO;
use RuntimeException;

/**
 * Validates the allocated identities, peer ports and elapsed times of a complete process list.
 *
 * Each server is bracketed independently with process-table reads. The current and repair
 * connections identify themselves with CONNECTION_ID(); an enabled scheduler must contribute
 * exactly one additional daemon. Client ports must be valid and unchanged in both samples.
 * Current-query time must follow SYSDATE minus the pinned timestamp; idle and daemon times
 * must lie between their sampled values. A sleeping repair connection can briefly
 * report -1 between two zero samples (observed on independent MySQL 8.4 servers);
 * only that exact clock bracket admits the negative value. Independently started
 * MySQL 8.0 servers also report a complete listing exactly one second below two
 * identical clock samples, including the current query, sleeping repair client
 * and daemon. That coherent shift is accepted only when every row demonstrates
 * it; individual or larger clock deviations remain failures. Only these validated
 * fields become interchangeable. A second observed cohort on independent 5.7 servers
 * retains the exact negative idle snapshots and one-second current-query boundary;
 * it applies only to a complete two-client listing without a daemon.
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
     * @param int|null $guard The repair connection's independently sampled identity, or no repair connection
     * @param bool $direct Whether these clients use the direct interface, which has no TCP peer port
     */
    public function __construct(public readonly Sample $before, public readonly ?int $guard, public readonly bool $direct = false)
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
        $expected = 1 + ($this->guard === null ? 0 : 1) + ($before->scheduler === 'ON' ? 1 : 0);
        if ($before->current !== $after->current || $before->current === $this->guard || $before->scheduler !== $after->scheduler || count($rows) !== $expected || array_keys($rows) !== array_keys($before->rows) || array_keys($rows) !== array_keys($after->rows) || (!isset($rows[$before->current]) || ($this->guard !== null && !isset($rows[$this->guard])))) {
            return null;
        }
        $coherent = $this->coherent($rows, $after) || $this->sampledBoundary($rows, $after);
        $result = [];
        foreach ($rows as $id => $row) {
            $role = $id === $before->current ? 'current' : ($id === $this->guard ? 'guard' : 'daemon');
            $first = $before->rows[$id];
            $last = $after->rows[$id];
            $lower = $role === 'current' ? $before->age : $first[5];
            $upper = $role === 'current' ? $after->age : $last[5];
            $host = $this->host($row, $first, $last, $role);
            $transient = $role === 'guard' && $row[4] === 'Sleep' && $first[4] === 'Sleep' && $last[4] === 'Sleep' && $lower === 0 && $upper === 0 && $row[5] === -1;
            if ($host === null || (!$coherent && !$transient && ($row[5] < $lower || $row[5] > $upper || ($role !== 'current' && $row[5] < 0))) || ($role === 'daemon' && ($row[1] !== 'event_scheduler' || $row[4] !== 'Daemon'))) {
                return null;
            }
            $result[] = ['{' . self::CONTRACT . ':' . $role . '}', $row[1], $host, $row[3], $row[4], '{' . self::CONTRACT . ':elapsed}', $row[6], $row[7]];
        }

        return $result;
    }

    /**
     * Checks a complete one-second clock shift against two identical independent samples.
     *
     * @param array<int, ProcessRow> $rows The unchanged indexed observation
     */
    public function coherent(array $rows, Sample $after): bool
    {
        if ($this->guard === null || !isset($rows[$this->guard], $rows[$this->before->current])) {
            return false;
        }
        foreach ($rows as $id => $row) {
            $first = $this->before->rows[$id] ?? null;
            $last = $after->rows[$id] ?? null;
            if ($first === null || $last === null) {
                return false;
            }
            $current = $id === $this->before->current;
            $lower = $current ? $this->before->age : $first[5];
            $upper = $current ? $after->age : $last[5];
            if ($lower !== $upper || $row[5] !== $lower - 1 || (!$current && $id !== $this->guard && $row[5] < 0)
                || ($id === $this->guard && ($row[4] !== 'Sleep' || $first[4] !== 'Sleep' || $last[4] !== 'Sleep' || $lower < 0))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks the sampled second boundary observed on two independent MySQL 5.7 servers.
     * The idle client may already read -1 in the independent snapshots. Only that
     * exact idle cohort and its one-second current-query lag are interchangeable.
     *
     * @param array<int, ProcessRow> $rows The complete unmodified observation
     */
    public function sampledBoundary(array $rows, Sample $after): bool
    {
        if ($this->guard === null || count($rows) !== 2) {
            return false;
        }
        $guard = $rows[$this->guard] ?? null;
        $first = $this->before->rows[$this->guard] ?? null;
        $last = $after->rows[$this->guard] ?? null;
        $current = $rows[$this->before->current] ?? null;
        if ($guard === null || $first === null || $last === null || $current === null
            || $guard[4] !== 'Sleep' || $first[4] !== 'Sleep' || $last[4] !== 'Sleep' || $guard[5] !== -1) {
            return false;
        }
        $clocks = [$first[5], $last[5]];
        $same = $this->before->age === $after->age;
        $next = $after->age === $this->before->age + 1;
        return ($same && in_array($clocks, [[-1, -1], [-1, 0]], true) && $current[5] === $this->before->age - 1)
            || ($next && $clocks === [0, -1] && $current[5] === $this->before->age);
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
        if ($this->direct) {
            return $row[2] !== '' && !str_contains($row[2], ':') ? $row[2] . ':{' . self::CONTRACT . ':port}' : null;
        }
        if (preg_match('/\A(.+):([1-9][0-9]*)\z/', $row[2], $parts) !== 1 || (int) $parts[2] > 65535) {
            return null;
        }

        return $parts[1] . ':{' . self::CONTRACT . ':port}';
    }
}
