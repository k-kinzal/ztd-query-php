<?php

declare(strict_types=1);

namespace Fuzz\Fixture;

use PDO;
use PDOException;
use RuntimeException;

/**
 * The tables and rows every differential input starts from, on both servers.
 *
 * The tables cover the common column types, keys, defaults and NULL; the rows hold NULL,
 * negative numbers, duplicates, letter case and accents. The canonical SQL Faker
 * identifier has an INT destination so grammar-generated writes reach conversion
 * and storage rather than always failing to open their target. Statistics are recalculated
 * synchronously, with background recalculation disabled, before FLUSH TABLES clears
 * setup handles on both servers. No generated statement or result is filtered.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-persistent-stats.html.
 */
final class Tables
{
    /**
     * The tables provided before every SQL Faker input.
     */
    public const TABLES = ['t1', 't2', '_sqlfaker_identifier'];

    /**
     * The column names provided by the fixture.
     */
    public const COLUMNS = ['id', 'a', 'b', 'c', 'd', 'e', 'f', 'name', 'flag', 'u', '_sqlfaker_identifier'];

    /**
     * Answers the statements that create and fill the tables.
     *
     * @return list<string>
     */
    public function statements(): array
    {
        return [
            'SET GLOBAL innodb_stats_auto_recalc = OFF',
            'SET timestamp = 1700000000',
            'CREATE TABLE _sqlfaker_identifier (_sqlfaker_identifier INT)',
            'INSERT INTO _sqlfaker_identifier VALUES (10)',
            'CREATE TABLE t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20), c DECIMAL(8,2), d DOUBLE, e DATE, f DATETIME)',
            "CREATE TABLE t2 (id INT PRIMARY KEY AUTO_INCREMENT, a INT NOT NULL DEFAULT 0, name VARCHAR(20) NOT NULL DEFAULT '', flag TINYINT, u BIGINT UNSIGNED, b TEXT, UNIQUE KEY uk (name))",
            "INSERT INTO t1 VALUES (1, 10, 'apple', 1.50, 0.5, '2024-01-31', '2024-01-31 10:20:30'), (2, -3, 'Banana', -2.25, -1e10, '2023-12-01', NULL), (3, NULL, NULL, NULL, NULL, NULL, NULL), (4, 10, 'apple ', 0.00, 1e-5, '2000-02-29', '1999-12-31 23:59:59'), (5, 7, 'Éclair', 99.99, 3.25, NULL, '2024-02-29 00:00:00')",
            "INSERT INTO t2 (a, name, flag, u, b) VALUES (1, 'x', 1, 18446744073709551615, 'text'), (10, 'Y', 0, 0, NULL), (-3, 'z', NULL, 42, 'apple'), (7, '', 1, NULL, 'Éclair')",
            'ANALYZE TABLE t1, t2, _sqlfaker_identifier',
            'FLUSH TABLES',
        ];
    }
    /**
     * Builds the common fixture through the text protocol and consumes its result.
     * This includes ANALYZE TABLE's status rows. The selected input protocol is
     * restored on success and failure; only the generated input tests preparation.
     *
     * @throws RuntimeException When a connection in silent mode refuses the setup statement
     */
    public function execute(PDO $pdo, string $sql): void
    {
        $emulate = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        try {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $statement = $pdo->query($sql);
            if ($statement === false) {
                throw new RuntimeException('A differential fixture statement failed: ' . json_encode($pdo->errorInfo()));
            }
            $statement->closeCursor();
        } finally {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
        }
    }

    /**
     * Removes the owned fixture database before restoring accounts and tablespaces.
     *
     * Only an absent database can make the modern READ ONLY reset unnecessary.
     * MySQL 5.x has no schema READ ONLY option.
     *
     * @throws RuntimeException When the database cannot be made writable or removed
     */
    public function remove(PDO $pdo, string $database, string $version): void
    {
        $name = '`' . str_replace('`', '``', $database) . '`';
        if (!str_starts_with($version, '5.')) {
            try {
                $reset = $pdo->exec('ALTER SCHEMA ' . $name . ' READ ONLY = 0');
                if ($reset === false && !in_array($pdo->errorInfo()[1] ?? null, [1049, 3503], true)) {
                    throw new RuntimeException('Cannot make the fixture database writable: ' . json_encode($pdo->errorInfo()));
                }
            } catch (PDOException $failure) {
                if (!in_array($failure->errorInfo[1] ?? null, [1049, 3503], true)) {
                    throw new RuntimeException('Cannot make the fixture database writable.', 0, $failure);
                }
            }
        }
        $this->execute($pdo, 'DROP DATABASE IF EXISTS ' . $name);
    }

}
