<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

use PDO;
use PgSql\Connection;

/**
 * Reads the actual parser environment before a campaign; incompatible versions or scanner modes fail setup.
 */
final class OracleEnvironment
{
    /**
     * @return array<string, string>
     * @throws InfrastructureFailure When the server does not match the selected grammar and scanner assumptions
     */
    public static function mysql(PDO $pdo, string $grammarVersion): array
    {
        $statement = $pdo->query('SELECT VERSION() AS version, @@sql_mode AS mode, @@character_set_connection AS encoding, @@collation_connection AS collation');
        $row = $statement === false ? false : $statement->fetch(PDO::FETCH_ASSOC);
        $configuration = self::fields($row, ['version', 'mode', 'encoding', 'collation']);
        if ('mysql-' . explode('-', $configuration['version'], 2)[0] !== $grammarVersion
            || $configuration['encoding'] !== 'utf8mb4'
            || array_intersect(explode(',', $configuration['mode']), ['ANSI_QUOTES', 'IGNORE_SPACE', 'NO_BACKSLASH_ESCAPES', 'HIGH_NOT_PRECEDENCE', 'PIPES_AS_CONCAT']) !== []) {
            throw new InfrastructureFailure('MySQL version or SQL mode does not match the declared scanner.');
        }
        return ['checkMode' => 'server-prepare', ...$configuration];
    }

    /**
     * @return array<string, string>
     * @throws InfrastructureFailure When PostgreSQL or its string configuration differs from the declared scanner
     */
    public static function pg(Connection $connection): array
    {
        $result = pg_query($connection, "SELECT current_setting('server_version') AS version, current_setting('client_encoding') AS encoding, current_setting('standard_conforming_strings') AS strings, current_setting('lc_messages') AS messages");
        $row = $result === false ? false : pg_fetch_assoc($result);
        if ($result !== false) {
            pg_free_result($result);
        }
        $configuration = self::fields($row, ['version', 'encoding', 'strings', 'messages']);
        if (explode(' ', $configuration['version'], 2)[0] !== '17.2' || $configuration['encoding'] !== 'UTF8' || $configuration['strings'] !== 'on') {
            throw new InfrastructureFailure('PostgreSQL version or string mode does not match the declared scanner.');
        }
        return ['checkMode' => 'extended-parse', ...$configuration];
    }

    /**
     * @return array<string, string>
     * @throws InfrastructureFailure When the SQLite library version or compile-option metadata is unavailable
     */
    public static function sqlite(PDO $pdo): array
    {
        $statement = $pdo->query('SELECT sqlite_version() AS version');
        $configuration = self::fields($statement === false ? false : $statement->fetch(PDO::FETCH_ASSOC), ['version']);
        $options = $pdo->query('PRAGMA compile_options');
        if ($configuration['version'] !== '3.47.2' || $options === false) {
            throw new InfrastructureFailure('SQLite verification requires exactly 3.47.2 and its compile options.');
        }
        $values = [];
        foreach ($options->fetchAll(PDO::FETCH_COLUMN) as $option) {
            if (!is_string($option)) {
                throw new InfrastructureFailure('Invalid SQLite compile option.');
            }
            $values[] = $option;
        }
        sort($values);
        return ['checkMode' => 'prepare-no-step', 'compileOptions' => implode(',', $values), ...$configuration];
    }

    /**
     * Validates external database metadata at this public boundary.
     * @param list<string> $names
     * @return array<string, string>
     * @throws InfrastructureFailure When metadata is incomplete or malformed
     */
    public static function fields(mixed $row, array $names): array
    {
        if (!is_array($row)) {
            throw new InfrastructureFailure('Cannot read parser environment.');
        }
        $configuration = [];
        foreach ($names as $name) {
            $value = $row[$name] ?? null;
            if (!is_string($value)) {
                throw new InfrastructureFailure('Invalid parser environment field: ' . $name);
            }
            $configuration[$name] = $value;
        }
        return $configuration;
    }

    /**
     * Identifies the complete verification implementation rather than only its top-level classifier.
     * @throws InfrastructureFailure When a verification source cannot be read
     */
    public static function revision(): string
    {
        $targets = glob(__DIR__ . '/*.php');
        $native = glob(__DIR__ . '/../oracles/*.c');
        $paths = [...($targets === false ? [] : $targets), ...($native === false ? [] : $native)];
        sort($paths);
        $hashes = [];
        foreach ($paths as $path) {
            $hash = hash_file('sha256', $path);
            if ($hash === false) {
                throw new InfrastructureFailure('Cannot identify the syntax oracle.');
            }
            $hashes[basename($path)] = $hash;
        }
        return hash('sha256', serialize($hashes));
    }

    /**
     * Resolves optional campaign settings while preserving an explicitly empty password.
     */
    public static function setting(string $name, string $default): string
    {
        $value = getenv($name);
        return $value === false ? $default : $value;
    }
}
