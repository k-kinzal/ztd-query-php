<?php

declare(strict_types=1);

namespace Fuzz\Target;

use PDO;

/**
 * Validates the two independently allocated identities in a plain SHOW VARIABLES result.
 *
 * The pseudo thread id must equal this connection's independently sampled CONNECTION_ID().
 * The statement id must be strictly between this server's statement ids sampled before and
 * after the observation. Only those validated identity values become interchangeable;
 * missing or duplicate names, malformed ids and all other observations stay exact.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html.
 */
final class StatementIdentities
{
    /**
     * The contract recorded only after both identities are independently validated.
     */
    public const CONTRACT = 'statement-id-between-samples-and-pseudo-id-equals-connection';

    /**
     * @param int $before The preceding statement's id
     * @param int $connection The connection id before SHOW VARIABLES
     */
    public function __construct(public readonly int $before, public readonly int $connection)
    {
    }

    /**
     * Selects only an unfiltered read of the session variables on modern releases.
     */
    public static function handles(string $sql, string $version): bool
    {
        return !str_starts_with($version, '5.') && preg_match('/\A\s*SHOW\s+(?:(?:SESSION|LOCAL)\s+)?VARIABLES\s*;?\s*\z/i', $sql) === 1;
    }

    /**
     * Samples the lower statement bound and connection identity before the target statement.
     */
    public static function capture(PDO $pdo): ?self
    {
        $statement = $pdo->query('SELECT @@statement_id, CONNECTION_ID()');
        $row = $statement === false ? false : $statement->fetch(PDO::FETCH_NUM);

        return is_array($row) && is_int($row[0] ?? null) && is_int($row[1] ?? null) ? new self($row[0], $row[1]) : null;
    }

    /**
     * Samples the upper bound after the target statement and validates its complete result.
     *
     * @param array<string, mixed> $observation
     * @return array<string, mixed>
     */
    public function comparable(PDO $pdo, array $observation): array
    {
        $statement = $pdo->query('SELECT @@statement_id');
        $after = $statement === false ? false : $statement->fetchColumn();

        return is_int($after) ? $this->validated($observation, $after) : $observation;
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
        if (!is_array($rows) || !is_array($columns) || count($columns) !== 2 || !is_array($columns[0] ?? null) || !is_array($columns[1] ?? null) || array_slice($columns[0], 0, 2) !== ['Variable_name', 'session_variables'] || array_slice($columns[1], 0, 2) !== ['Value', 'session_variables']) {
            return $observation;
        }
        $found = $this->positions($rows, $after);
        if ($found === null) {
            return $observation;
        }
        foreach ($found as $name => $index) {
            $rows[$index] = [$name, '{' . self::CONTRACT . ':' . $name . '}'];
        }
        $results[0]['rows'] = $rows;
        $observation['results'] = $results;
        $observation['contracts'] = [self::CONTRACT];

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
            if (!in_array($name, ['statement_id', 'pseudo_thread_id'], true)) {
                continue;
            }
            if (isset($found[$name]) || preg_match('/\A[1-9][0-9]*\z/', $row[1]) !== 1 || (string) (int) $row[1] !== $row[1]) {
                return null;
            }
            $value = (int) $row[1];
            if ($name === 'statement_id' ? $value <= $this->before || $value >= $after : $value !== $this->connection) {
                return null;
            }
            $found[$name] = $index;
        }
        return count($found) === 2 ? $found : null;
    }
}
