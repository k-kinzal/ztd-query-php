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
    public function observe(PDO $pdo, string $sql, bool $ordered, ?Record $record = null, ?Traffic $traffic = null): array
    {
        $observation = ['results' => []];
        if ($record !== null) {
            $record->phase = 'statement';
            $record->raw = $observation;
        }
        try {
            try {
                $executed = true;
                if ($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) === true) {
                    $traffic?->ready($record);
                    $statement = $pdo->query($sql);
                } else {
                    $statement = $pdo->prepare($sql);
                    if ($statement !== false) {
                        $traffic?->ready($record);
                        $executed = $statement->execute();
                    }
                }
                if ($statement === false || !$executed) {
                    $error = $statement === false ? $pdo->errorInfo() : $statement->errorInfo();
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
                }
            } finally {
                $traffic?->complete($record);
            }
            if ($statement !== false) {
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
    public function probe(PDO $pdo, string $sql, ?Record $owner = null, ?Traffic $traffic = null): array
    {
        $emulate = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        $record = $owner?->probe($sql) ?? new Record('probe', $sql, '');
        try {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $observation = $this->observe($pdo, $sql, true, $record, $traffic);
            $record->phase = 'state validation';
            if (isset($observation['error'])) {
                throw new RuntimeException('The state probe returned a SQL error.');
            }
            $this->rowset($observation);
            $record->phase = 'complete';
            return $observation;
        } catch (RuntimeException $failure) {
            throw new ProbeFailure($record, $failure);
        } finally {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
        }
    }

    /**
     * Reads required state rows while keeping metadata and conditions in the owning record.
     *
     * @return array<mixed>
     * @throws RuntimeException When the state read cannot provide one row set
     */
    public function rows(PDO $pdo, string $sql, ?Record $owner = null, ?Traffic $traffic = null): array
    {
        return $this->rowset($this->probe($pdo, $sql, $owner, $traffic));
    }

    /**
     * Requires one row set before a state reader interprets its contents.
     *
     * @param array<string, mixed> $observation The complete driver observation
     * @return array<mixed>
     * @throws RuntimeException When the state read did not return one row set
     */
    public function rowset(array $observation): array
    {
        $results = $observation['results'] ?? null;
        if (!is_array($results) || count($results) !== 1 || !is_array($results[0] ?? null) || !is_array($results[0]['rows'] ?? null)) {
            throw new RuntimeException('The state probe did not return one row set.');
        }
        return $results[0]['rows'];
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
    public function tables(PDO $pdo, string $database, ?Record $owner = null): array
    {
        $tables = [];
        try {
            try {
                $names = $this->rows($pdo, 'SHOW FULL TABLES FROM ' . $this->identifier($database) . " WHERE Table_type = 'BASE TABLE'", $owner);
            } catch (ProbeFailure $failure) {
                if ($failure->absentDatabase()) {
                    return [];
                }
                throw $failure;
            }
            foreach ($names as $row) {
                if (!is_array($row) || !is_string($row[0] ?? null)) {
                    throw new RuntimeException('The table inventory returned an invalid identifier.');
                }
                $name = $row[0];
                $tables[$name] = $this->bag($this->rows($pdo, 'SELECT * FROM ' . $this->identifier($database) . '.' . $this->identifier($name), $owner));
            }
        } catch (RuntimeException $failure) {
            // Only an absent initial inventory establishes DROP DATABASE, not a later read failure.
            throw new RuntimeException('Cannot read observed table state.', 0, $failure);
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
    public function definitions(PDO $pdo, string $database, ?Record $owner = null): ?array
    {
        try {
            try {
                $schema = $this->rows($pdo, 'SHOW CREATE DATABASE ' . $this->identifier($database), $owner);
            } catch (ProbeFailure $failure) {
                if ($failure->absentDatabase()) {
                    return null;
                }
                throw $failure;
            }
            $definitions = ['database' => $schema, 'relations' => []];
            $names = $this->rows($pdo, 'SHOW TABLES FROM ' . $this->identifier($database), $owner);
            foreach ($names as $row) {
                if (!is_array($row) || !is_string($row[0] ?? null)) {
                    throw new RuntimeException('The definition inventory returned an invalid identifier.');
                }
                $name = $row[0];
                $definitions['relations'][$name] = $this->rows($pdo, 'SHOW CREATE TABLE ' . $this->identifier($database) . '.' . $this->identifier($name), $owner);
            }
        } catch (RuntimeException $failure) {
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
