<?php

declare(strict_types=1);

namespace Fuzz\Target;

use PDO;
use PDOException;
use PDOStatement;

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
     */
    public function tables(PDO $pdo, string $database): array
    {
        $tables = [];
        try {
            $names = $pdo->query('SHOW TABLES FROM `' . $database . '`');
            foreach ($names === false ? [] : $names->fetchAll(PDO::FETCH_COLUMN) as $name) {
                if (!is_string($name)) {
                    continue;
                }
                $rows = $pdo->query('SELECT * FROM `' . $database . '`.`' . $name . '`');
                $tables[$name] = $rows === false ? null : $this->bag($rows->fetchAll(PDO::FETCH_NUM));
            }
        } catch (PDOException $failure) {
            $tables['error'] = $failure->getMessage();
        }
        ksort($tables);

        return $tables;
    }
}
