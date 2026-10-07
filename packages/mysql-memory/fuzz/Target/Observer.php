<?php

declare(strict_types=1);

namespace Fuzz\Target;

use PDO;
use PDOException;

/**
 * Runs one statement through PDO and records everything a client observes of it.
 *
 * A success records the result columns (name, table, native type, length, precision, flags), the
 * rows as PDO returns them, or the affected-row count; a failure records the error number,
 * SQLSTATE and message. The warnings of the statement and the rows of every table afterwards are
 * recorded too.
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
        try {
            $statement = $pdo->query($sql);
            if ($statement === false) {
                return ['error' => 'query returned false'];
            }
            if ($statement->columnCount() === 0) {
                $observation = ['affected' => $statement->rowCount()];
            } else {
                $columns = [];
                for ($i = 0; $i < $statement->columnCount(); $i++) {
                    $meta = $statement->getColumnMeta($i);
                    $columns[] = $meta === false ? null : [$meta['name'], $meta['table'] ?? '', $meta['native_type'] ?? '', $meta['len'], $meta['precision'], $meta['flags']];
                }
                $rows = $statement->fetchAll(PDO::FETCH_NUM);
                $observation = ['columns' => $columns, 'rows' => $ordered ? $rows : $this->bag($rows)];
            }
            while ($statement->nextRowset()) {
            }
            $statement->closeCursor();
        } catch (PDOException $failure) {
            $observation = ['error' => [$failure->errorInfo[1] ?? null, $failure->errorInfo[0] ?? null, $failure->errorInfo[2] ?? $failure->getMessage()]];
        }
        $observation['warnings'] = $this->warnings($pdo);

        return $observation;
    }

    /**
     * Answers the rows as a multiset: sorted by their serialization.
     *
     * @param list<list<mixed>> $rows
     * @return list<list<mixed>>
     */
    public function bag(array $rows): array
    {
        usort($rows, static fn (array $left, array $right): int => serialize($left) <=> serialize($right));

        return $rows;
    }

    /**
     * Answers the warnings of the last statement.
     *
     * @return list<list<mixed>>|string
     */
    public function warnings(PDO $pdo): array|string
    {
        try {
            $statement = $pdo->query('SHOW WARNINGS');

            return $statement === false ? 'none' : $statement->fetchAll(PDO::FETCH_NUM);
        } catch (PDOException $failure) {
            return 'SHOW WARNINGS failed: ' . $failure->getMessage();
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
