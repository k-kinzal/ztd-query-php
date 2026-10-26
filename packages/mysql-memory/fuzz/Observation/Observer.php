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
     * @throws RuntimeException When result metadata or conditions cannot be read
     */
    public function observe(PDO $pdo, string $sql, bool $ordered, ?Record $record = null): array
    {
        $observation = ['results' => []];
        if ($record !== null) {
            $record->phase = 'statement';
            $record->raw = $observation;
        }
        try {
            $statement = $pdo->query($sql);
            if ($statement === false) {
                $error = $pdo->errorInfo();
                $observation['error'] = [$error[1] ?? null, $error[0], $error[2] ?? null];
            } else {
                do {
                    $observation['results'][] = $this->result($pdo, $statement, $ordered);
                    if ($record !== null) {
                        $record->raw = $observation;
                    }
                } while ($statement->nextRowset());
                if ($statement->errorCode() !== '00000') {
                    $error = $statement->errorInfo();
                    $observation['error'] = [$error[1] ?? null, $error[0], $error[2] ?? null];
                }
                $statement->closeCursor();
            }
        } catch (PDOException $failure) {
            $observation['error'] = [$failure->errorInfo[1] ?? null, $failure->errorInfo[0] ?? null, $failure->errorInfo[2] ?? $failure->getMessage()];
        }
        if ($record !== null) {
            $record->phase = 'conditions';
            $record->raw = $observation;
        }
        $observation['warnings'] = $this->warnings($pdo);
        if ($record !== null) {
            $record->raw = $observation;
        }

        return $observation;
    }

    /**
     * Requires a successful state read, retaining failed probes as lossless evidence.
     *
     * Fixed observer SQL uses the text protocol independently of the generated input.
     * Matching SQL errors from two probes cannot establish equality of unread state.
     *
     * @return array<string, mixed>
     * @throws RuntimeException When the probe or its conditions cannot be read
     */
    public function probe(PDO $pdo, string $sql): array
    {
        $emulate = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        $record = new Record('probe', $sql, '');
        try {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $observation = $this->observe($pdo, $sql, true, $record);
            $record->phase = 'state validation';
            if (isset($observation['error'])) {
                throw new RuntimeException('The state probe returned a SQL error.');
            }
            $results = $observation['results'];
            if (!is_array($results) || count($results) !== 1 || !is_array($results[0]) || !is_array($results[0]['rows'] ?? null)) {
                throw new RuntimeException('The state probe did not return one row set.');
            }
            return $observation;
        } catch (RuntimeException $failure) {
            throw new RuntimeException('Cannot read observed state: ' . $sql . '; base64 serialized probe: ' . base64_encode(serialize($record)), 0, $failure);
        } finally {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
        }
    }

    /**
     * Records the current result before advancing to the next one, which can change the connection's last insert id.
     *
     * @return array<string, mixed>
     * @throws RuntimeException When result metadata cannot be observed
     */
    public function result(PDO $pdo, PDOStatement $statement, bool $ordered): array
    {
        if ($statement->columnCount() === 0) {
            return ['affected' => $statement->rowCount(), 'lastInsertId' => $pdo->lastInsertId()];
        }
        $columns = [];
        for ($i = 0; $i < $statement->columnCount(); $i++) {
            $meta = $statement->getColumnMeta($i);
            if ($meta === false) {
                throw new RuntimeException('Cannot read observed result metadata for column ' . $i . '.');
            }
            $columns[] = [$meta['name'], $meta['table'] ?? '', $meta['native_type'] ?? '', $meta['len'], $meta['precision'], $meta['flags']];
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
     * @return array<mixed>
     * @throws RuntimeException When conditions cannot be read; matching read failures are not agreement
     */
    public function warnings(PDO $pdo): array
    {
        $emulate = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        try {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $statement = $pdo->query('SHOW WARNINGS');
            if ($statement === false) {
                throw new RuntimeException('Cannot read observed conditions: ' . json_encode($pdo->errorInfo()));
            }
            return $statement->fetchAll(PDO::FETCH_NUM);
        } catch (PDOException $failure) {
            throw new RuntimeException('Cannot read observed conditions.', 0, $failure);
        } finally {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
        }
    }

    /**
     * Answers the rows of every base table, each sorted as a multiset.
     * Views are observed as definitions; evaluating one can invoke a stored function
     * and change the state being measured.
     *
     * @return array<string, mixed>
     * @throws RuntimeException When table state cannot be read
     */
    public function tables(PDO $pdo, string $database): array
    {
        $tables = [];
        $emulate = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        try {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            try {
                $names = $pdo->query('SHOW FULL TABLES FROM ' . $this->identifier($database) . " WHERE Table_type = 'BASE TABLE'");
            } catch (PDOException $failure) {
                if (($failure->errorInfo[1] ?? null) === 1049) {
                    return [];
                }
                throw $failure;
            }
            if ($names === false) {
                if (($pdo->errorInfo()[1] ?? null) === 1049) {
                    return [];
                }
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
            // Only an absent initial inventory establishes DROP DATABASE, not a later read failure.
            throw new RuntimeException('Cannot read observed table state.', 0, $failure);
        } finally {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
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
        $emulate = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        try {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            try {
                $schema = $pdo->query('SHOW CREATE DATABASE ' . $this->identifier($database));
            } catch (PDOException $failure) {
                if (($failure->errorInfo[1] ?? null) === 1049) {
                    return null;
                }
                throw $failure;
            }
            if ($schema === false) {
                if (($pdo->errorInfo()[1] ?? null) === 1049) {
                    return null;
                }
                throw new RuntimeException('Cannot enumerate observed definitions: ' . json_encode($pdo->errorInfo()));
            }
            $definitions = ['database' => $schema->fetchAll(PDO::FETCH_NUM), 'relations' => []];
            $names = $pdo->query('SHOW TABLES FROM ' . $this->identifier($database));
            if ($names === false) {
                throw new RuntimeException('Cannot enumerate observed definitions: ' . json_encode($pdo->errorInfo()));
            }
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
            throw new RuntimeException('Cannot read observed definitions.', 0, $failure);
        } finally {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
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
