<?php

declare(strict_types=1);

namespace Fuzz\Target;

/**
 * The tables and rows every differential input starts from, on both servers.
 *
 * The tables cover the common column types, keys, defaults and NULL; the rows hold NULL,
 * negative numbers, duplicates, letter case and accents.
 */
final class Fixture
{
    /**
     * The table names generated statements are constrained to.
     */
    public const TABLES = ['t1', 't2'];

    /**
     * The column names generated statements are constrained to.
     */
    public const COLUMNS = ['id', 'a', 'b', 'c', 'd', 'e', 'f', 'name', 'flag', 'u'];

    /**
     * Answers the statements that create and fill the tables.
     *
     * @return list<string>
     */
    public function statements(): array
    {
        return [
            'SET timestamp = 1700000000',
            'CREATE TABLE t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20), c DECIMAL(8,2), d DOUBLE, e DATE, f DATETIME)',
            "CREATE TABLE t2 (id INT PRIMARY KEY AUTO_INCREMENT, a INT NOT NULL DEFAULT 0, name VARCHAR(20) NOT NULL DEFAULT '', flag TINYINT, u BIGINT UNSIGNED, b TEXT, UNIQUE KEY uk (name))",
            "INSERT INTO t1 VALUES (1, 10, 'apple', 1.50, 0.5, '2024-01-31', '2024-01-31 10:20:30'), (2, -3, 'Banana', -2.25, -1e10, '2023-12-01', NULL), (3, NULL, NULL, NULL, NULL, NULL, NULL), (4, 10, 'apple ', 0.00, 1e-5, '2000-02-29', '1999-12-31 23:59:59'), (5, 7, 'Éclair', 99.99, 3.25, NULL, '2024-02-29 00:00:00')",
            "INSERT INTO t2 (a, name, flag, u, b) VALUES (1, 'x', 1, 18446744073709551615, 'text'), (10, 'Y', 0, 0, NULL), (-3, 'z', NULL, 42, 'apple'), (7, '', 1, NULL, 'Éclair')",
        ];
    }
}
