<?php

declare(strict_types=1);

/**
 * Supported SQL releases and the resources generated for each of them.
 *
 * @return array<string, array{default: string, versions: array<string, array{table: string, keywords: string}>}>
 */
return [
    'mysql' => [
        'default' => 'mysql-8.4.7',
        'versions' => [
            'mysql-5.6.51' => ['table' => 'tables/mysql-5.6.51.bin', 'keywords' => 'keywords/mysql-5.6.51.php'],
            'mysql-5.7.44' => ['table' => 'tables/mysql-5.7.44.bin', 'keywords' => 'keywords/mysql-5.7.44.php'],
            'mysql-8.0.44' => ['table' => 'tables/mysql-8.0.44.bin', 'keywords' => 'keywords/mysql-8.0.44.php'],
            'mysql-8.1.0' => ['table' => 'tables/mysql-8.1.0.bin', 'keywords' => 'keywords/mysql-8.1.0.php'],
            'mysql-8.2.0' => ['table' => 'tables/mysql-8.2.0.bin', 'keywords' => 'keywords/mysql-8.2.0.php'],
            'mysql-8.3.0' => ['table' => 'tables/mysql-8.3.0.bin', 'keywords' => 'keywords/mysql-8.3.0.php'],
            'mysql-8.4.7' => ['table' => 'tables/mysql-8.4.7.bin', 'keywords' => 'keywords/mysql-8.4.7.php'],
            'mysql-9.0.1' => ['table' => 'tables/mysql-9.0.1.bin', 'keywords' => 'keywords/mysql-9.0.1.php'],
            'mysql-9.1.0' => ['table' => 'tables/mysql-9.1.0.bin', 'keywords' => 'keywords/mysql-9.1.0.php'],
        ],
    ],
    'postgresql' => [
        'default' => 'pg-17.2',
        'versions' => [
            'pg-17.2' => ['table' => 'tables/pg-17.2.bin', 'keywords' => 'keywords/pg-17.2.php'],
        ],
    ],
    'sqlite' => [
        'default' => 'sqlite-3.47.2',
        'versions' => [
            'sqlite-3.47.2' => ['table' => 'tables/sqlite-3.47.2.bin', 'keywords' => 'keywords/sqlite-3.47.2.php'],
        ],
    ],
];
