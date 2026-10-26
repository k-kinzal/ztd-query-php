<?php

declare(strict_types=1);

namespace Fuzz\Observation;

use PDO;
use RuntimeException;

/**
 * Validates independently allocated identities in a plain SHOW VARIABLES result.
 *
 * The pseudo thread id must equal this connection's independently sampled CONNECTION_ID().
 * The statement id must be strictly between this server's statement ids sampled before and
 * after the observation. Only those validated identity values become interchangeable;
 * missing or duplicate names, malformed ids and all other observations stay exact.
 * MySQL 5.x exposes only the pseudo thread id, checked against CONNECTION_ID().
 * Source: https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html.
 */
final class StatementIdentities
{
    /**
     * The contract recorded only after both identities are independently validated.
     */
    public const CONTRACT = 'statement-id-between-samples-and-pseudo-id-equals-connection';

    /**
     * The legacy contract validates the allocated connection identity only.
     */
    public const LEGACY_CONTRACT = 'pseudo-id-equals-connection';

    /**
     * @param int|null $before The preceding statement id, absent on MySQL 5.x
     * @param int $connection The connection id before SHOW VARIABLES
     * @param string $table The release's result-column origin table
     */
    public function __construct(public readonly ?int $before, public readonly int $connection, public readonly string $table = 'session_variables')
    {
    }

    /**
     * Selects only an unfiltered read of the session variables on supported releases.
     */
    public static function handles(string $sql, string $version): bool
    {
        return preg_match('/\A\s*SHOW\s+(?:(?:SESSION|LOCAL)\s+)?VARIABLES\s*;?\s*\z/i', $sql) === 1;
    }

    /**
     * Samples the lower statement bound and connection identity before the target statement.
     *
     * @throws RuntimeException When the identity sample is unreadable or malformed
     */
    public static function capture(PDO $pdo, string $version, ?Record $record = null): self
    {
        $observer = new Observer();
        if (str_starts_with($version, '5.')) {
            $rows = $observer->rows($pdo, 'SELECT CONNECTION_ID()', $record);
            if (count($rows) !== 1 || !is_array($rows[0]) || !is_int($rows[0][0] ?? null)) {
                throw new RuntimeException('Cannot sample the observed connection identity.');
            }
            return new self(null, $rows[0][0], str_starts_with($version, '5.6.') ? 'VARIABLES' : 'session_variables');
        }
        $rows = $observer->rows($pdo, 'SELECT @@statement_id, CONNECTION_ID()', $record);
        if (count($rows) !== 1 || !is_array($rows[0]) || !is_int($rows[0][0] ?? null) || !is_int($rows[0][1] ?? null)) {
            throw new RuntimeException('Cannot sample the observed statement and connection identities.');
        }
        return new self($rows[0][0], $rows[0][1]);
    }

    /**
     * Samples the upper bound after the target statement and validates its complete result.
     *
     * @param array<string, mixed> $observation
     * @return array<string, mixed>
     * @throws RuntimeException When the upper identity bound is unreadable or malformed
     */
    public function comparable(PDO $pdo, array $observation, ?Record $record = null): array
    {
        if ($this->before === null) {
            return $this->validated($observation, 0);
        }
        $rows = (new Observer())->rows($pdo, 'SELECT @@statement_id', $record);
        if (count($rows) !== 1 || !is_array($rows[0]) || !is_int($rows[0][0] ?? null)) {
            throw new RuntimeException('Cannot sample the observed statement identity upper bound.');
        }
        return $this->validated($observation, $rows[0][0]);
    }

    /**
     * Replaces only unique, well-formed identity rows satisfying their independent bounds.
     *
     * @param array<string, mixed> $observation
     * @return array<string, mixed>
     */
    public function validated(array $observation, int $after): array
    {
        $results = $observation['results'] ?? null;
        if (isset($observation['error']) || !is_array($results) || count($results) !== 1 || !is_array($results[0] ?? null)) {
            return $observation;
        }
        $rows = $results[0]['rows'] ?? null;
        $columns = $results[0]['columns'] ?? null;
        $table = $this->table;
        if (!is_array($rows) || !is_array($columns) || count($columns) !== 2 || !is_array($columns[0] ?? null) || !is_array($columns[1] ?? null) || array_slice($columns[0], 0, 2) !== ['Variable_name', $table] || array_slice($columns[1], 0, 2) !== ['Value', $table]) {
            return $observation;
        }
        $found = $this->positions($rows, $after);
        if ($found === null) {
            return $observation;
        }
        $contract = $this->before === null ? self::LEGACY_CONTRACT : self::CONTRACT;
        foreach ($found as $name => $index) {
            $rows[$index] = [$name, '{' . $contract . ':' . $name . '}'];
        }
        $results[0]['rows'] = $rows;
        $observation['results'] = $results;
        $observation['contracts'] = [$contract];

        return $observation;
    }

    /**
     * Locates the two unique identity rows after checking their bounds.
     *
     * @param array<mixed> $rows
     * @return array<string, int|string>|null
     */
    public function positions(array $rows, int $after): ?array
    {
        $found = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row) || count($row) !== 2 || !is_string($row[0] ?? null) || !is_string($row[1] ?? null)) {
                return null;
            }
            $name = $row[0];
            if (!in_array($name, $this->before === null ? ['pseudo_thread_id'] : ['statement_id', 'pseudo_thread_id'], true)) {
                continue;
            }
            if (isset($found[$name]) || preg_match('/\A[1-9][0-9]*\z/', $row[1]) !== 1 || (string) (int) $row[1] !== $row[1]) {
                return null;
            }
            $value = (int) $row[1];
            if ($name === 'statement_id' ? $this->before === null || $value <= $this->before || $value >= $after : $value !== $this->connection) {
                return null;
            }
            $found[$name] = $index;
        }
        return count($found) === ($this->before === null ? 1 : 2) ? $found : null;
    }
}
