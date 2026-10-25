<?php

declare(strict_types=1);

namespace Fuzz\Observation;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Runs one statement through PDO and records the results, errors and warnings exposed by the driver.
 *
 * A success records the result columns (name, table, native type, length, precision, flags), the
 * rows as PDO returns them, or the affected-row count and last insert id, for every result set.
 * A failure records the error number, SQLSTATE and message without discarding earlier results.
 * The warnings of the statement are recorded too; tables() snapshots a database separately.
 */
final class Observer
{
    /**
     * Answers the observation of a statement on a connection.
     *
     * @return array<string, mixed>
     */
    public function observe(PDO $pdo, string $sql, bool $ordered): array
    {
        $observation = ['results' => []];
        try {
            $statement = $pdo->query($sql);
            if ($statement === false) {
                return ['error' => 'query returned false'];
            }
            do {
                $observation['results'][] = $this->result($pdo, $statement, $ordered);
            } while ($statement->nextRowset());
            $statement->closeCursor();
        } catch (PDOException $failure) {
            $observation['error'] = [$failure->errorInfo[1] ?? null, $failure->errorInfo[0] ?? null, $failure->errorInfo[2] ?? $failure->getMessage()];
        }
        $observation['warnings'] = $this->warnings($pdo);

        return $observation;
    }

    /**
     * Records the current result before advancing to the next one, which can change the connection's last insert id.
     *
     * @return array<string, mixed>
     */
    public function result(PDO $pdo, PDOStatement $statement, bool $ordered): array
    {
        if ($statement->columnCount() === 0) {
            return ['affected' => $statement->rowCount(), 'lastInsertId' => $pdo->lastInsertId()];
        }
        $columns = [];
        for ($i = 0; $i < $statement->columnCount(); $i++) {
            $meta = $statement->getColumnMeta($i);
            $columns[] = $meta === false ? null : [$meta['name'], $meta['table'] ?? '', $meta['native_type'] ?? '', $meta['len'], $meta['precision'], $meta['flags']];
        }
        $rows = $statement->fetchAll(PDO::FETCH_NUM);

        return ['columns' => $columns, 'rows' => $ordered ? $rows : $this->bag($rows)];
    }

    /**
     * Answers the rows as a multiset: sorted by their serialization.
     *
     * @param array<mixed> $rows
     * @return list<mixed>
     */
    public function bag(array $rows): array
    {
        $keys = [];
        foreach ($rows as $index => $row) {
            $keys[$index] = serialize($row);
        }
        asort($keys);
        $sorted = [];
        foreach (array_keys($keys) as $index) {
            $sorted[] = $rows[$index];
        }

        return $sorted;
    }

    /**
     * Answers the warnings of the last statement.
     *
     * @return array<mixed>|string
     */
    public function warnings(PDO $pdo): array|string
    {
        $emulate = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        try {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $statement = $pdo->query('SHOW WARNINGS');

            return $statement === false ? 'none' : $statement->fetchAll(PDO::FETCH_NUM);
        } catch (PDOException $failure) {
            return 'SHOW WARNINGS failed: ' . $failure->getMessage();
        } finally {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
        }
    }

    /**
     * Answers the rows of every table of a database, each table sorted as a multiset.
     *
     * @return array<string, mixed>
     * @throws RuntimeException When table state cannot be read
     */
    public function tables(PDO $pdo, string $database): array
    {
        $tables = [];
        try {
            $names = $pdo->query('SHOW TABLES FROM ' . $this->identifier($database));
            if ($names === false) {
                throw new RuntimeException('Cannot enumerate observed tables: ' . json_encode($pdo->errorInfo()));
            }
            foreach ($names->fetchAll(PDO::FETCH_COLUMN) as $name) {
                if (!is_string($name)) {
                    throw new RuntimeException('The table inventory returned an invalid identifier.');
                }
                $rows = $pdo->query('SELECT * FROM ' . $this->identifier($database) . '.' . $this->identifier($name));
                if ($rows === false) {
                    throw new RuntimeException('Cannot read observed table ' . $name . ': ' . json_encode($pdo->errorInfo()));
                }
                $tables[$name] = $this->bag($rows->fetchAll(PDO::FETCH_NUM));
            }
        } catch (PDOException $failure) {
            if (($failure->errorInfo[1] ?? null) !== 1049) {
                throw new RuntimeException('Cannot read observed table state.', 0, $failure);
            }
            // DROP DATABASE is an observable state, distinct from an unreadable catalog.
            return [];
        }
        ksort($tables);

        return $tables;
    }

    /**
     * Records schema, table and view definitions, including defaults, keys and constraints.
     *
     * @return array<string, mixed>|null Null means the observed database was dropped
     * @throws RuntimeException When a definition cannot be read
     */
    public function definitions(PDO $pdo, string $database): ?array
    {
        try {
            $schema = $pdo->query('SHOW CREATE DATABASE ' . $this->identifier($database));
            $names = $pdo->query('SHOW TABLES FROM ' . $this->identifier($database));
            if ($schema === false || $names === false) {
                throw new RuntimeException('Cannot enumerate observed definitions: ' . json_encode($pdo->errorInfo()));
            }
            $definitions = ['database' => $schema->fetchAll(PDO::FETCH_NUM), 'relations' => []];
            foreach ($names->fetchAll(PDO::FETCH_COLUMN) as $name) {
                if (!is_string($name)) {
                    throw new RuntimeException('The definition inventory returned an invalid identifier.');
                }
                $definition = $pdo->query('SHOW CREATE TABLE ' . $this->identifier($database) . '.' . $this->identifier($name));
                if ($definition === false) {
                    throw new RuntimeException('Cannot read observed definition ' . $name . ': ' . json_encode($pdo->errorInfo()));
                }
                $definitions['relations'][$name] = $definition->fetchAll(PDO::FETCH_NUM);
            }
        } catch (PDOException $failure) {
            if (($failure->errorInfo[1] ?? null) === 1049) {
                return null;
            }
            throw new RuntimeException('Cannot read observed definitions.', 0, $failure);
        }
        ksort($definitions['relations']);

        return $definitions;
    }

    /**
     * Quotes a name read from a catalog, including embedded identifier quotes.
     */
    public function identifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
