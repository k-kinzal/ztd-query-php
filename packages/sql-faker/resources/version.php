<?php

declare(strict_types=1);

/**
 * Versioned SQL grammar and lexical profile resources.
 *
 * @return array<string, array{default: string, versions: array<string, array{ast: string, lexical: string}>}>
 */
return [
    'mysql' => [
        'default' => 'mysql-8.4.7',
        'versions' => [
            'mysql-5.6.51' => ['ast' => 'ast/mysql-5.6.51.php', 'lexical' => 'lexical/mysql-5.6.51.php'],
            'mysql-5.7.44' => ['ast' => 'ast/mysql-5.7.44.php', 'lexical' => 'lexical/mysql-5.7.44.php'],
            'mysql-8.0.44' => ['ast' => 'ast/mysql-8.0.44.php', 'lexical' => 'lexical/mysql-8.0.44.php'],
            'mysql-8.1.0' => ['ast' => 'ast/mysql-8.1.0.php', 'lexical' => 'lexical/mysql-8.1.0.php'],
            'mysql-8.2.0' => ['ast' => 'ast/mysql-8.2.0.php', 'lexical' => 'lexical/mysql-8.2.0.php'],
            'mysql-8.3.0' => ['ast' => 'ast/mysql-8.3.0.php', 'lexical' => 'lexical/mysql-8.3.0.php'],
            'mysql-8.4.7' => ['ast' => 'ast/mysql-8.4.7.php', 'lexical' => 'lexical/mysql-8.4.7.php'],
            'mysql-9.0.1' => ['ast' => 'ast/mysql-9.0.1.php', 'lexical' => 'lexical/mysql-9.0.1.php'],
            'mysql-9.1.0' => ['ast' => 'ast/mysql-9.1.0.php', 'lexical' => 'lexical/mysql-9.1.0.php'],
        ],
    ],
    'postgresql' => [
        'default' => 'pg-17.2',
        'versions' => [
            'pg-17.2' => ['ast' => 'ast/pg-17.2.php', 'lexical' => 'lexical/pg-17.2.php'],
        ],
    ],
    'sqlite' => [
        'default' => 'sqlite-3.47.2',
        'versions' => [
            'sqlite-3.47.2' => ['ast' => 'ast/sqlite-3.47.2.php', 'lexical' => 'lexical/sqlite-3.47.2.php'],
        ],
    ],
];
